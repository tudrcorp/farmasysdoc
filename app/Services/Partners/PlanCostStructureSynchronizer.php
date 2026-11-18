<?php

namespace App\Services\Partners;

use App\Enums\PlanPremiumMode;
use App\Models\PartnerCompanyPlan;
use App\Models\Plan;
use App\Models\PlanAffiliateBand;
use App\Models\PlanAffiliateBandPrice;
use App\Models\PlanSubplan;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Estructura de costos de los planes por cantidad de afiliados: subplanes (con sus servicios
 * y catálogos), tramos de afiliados y la matriz de precios tramo × subplan.
 *
 * En el formulario cada subplan se identifica por su uuid (`key`) para poder ligar las
 * columnas de precios a subplanes que todavía no existen en la base.
 */
class PlanCostStructureSynchronizer
{
    /**
     * Clave de la columna de precios cuando el plan no maneja subplanes.
     */
    public const BASE_PRICE_KEY = 'base';

    /**
     * Estado del formulario a partir del plan guardado.
     *
     * @return array{subplans: array<string, array<string, mixed>>, affiliate_bands: list<array<string, mixed>>}
     */
    public function toFormState(Plan $plan): array
    {
        $plan->loadMissing(['subplans.services', 'subplans.formularies', 'affiliateBands.prices']);

        $keysById = $plan->subplans->mapWithKeys(fn (PlanSubplan $subplan): array => [$subplan->getKey() => $subplan->uuid]);

        $subplans = [];
        foreach ($plan->subplans as $subplan) {
            $subplans[$subplan->uuid] = [
                'key' => $subplan->uuid,
                'name' => $subplan->name,
                'description' => $subplan->description,
                'services' => $subplan->services->pluck('id')->map(fn (mixed $id): string => (string) $id)->all(),
                'formularies' => $subplan->formularies->pluck('id')->map(fn (mixed $id): string => (string) $id)->all(),
            ];
        }

        $bands = $plan->affiliateBands->map(fn (PlanAffiliateBand $band): array => [
            'min_affiliates' => $band->min_affiliates,
            'max_affiliates' => $band->max_affiliates,
            'prices' => $band->prices->mapWithKeys(fn (PlanAffiliateBandPrice $price): array => [
                ($price->plan_subplan_id === null ? self::BASE_PRICE_KEY : $keysById->get($price->plan_subplan_id)) => (string) $price->price_usd,
            ])->filter(fn (mixed $value, mixed $key): bool => $key !== null && $key !== '')->all(),
        ])->values()->all();

        return ['subplans' => $subplans, 'affiliate_bands' => $bands];
    }

    /**
     * Motivo por el que no se puede guardar el cambio de estructura, o null si se puede.
     * Protege a los aliados ya contratados con el baremo o con un subplan.
     *
     * @param  array<string, mixed>  $state  Estado crudo del formulario.
     */
    public function blockingReason(Plan $plan, array $state): ?string
    {
        $mode = $state['premium_mode'] ?? null;
        $mode = $mode instanceof BackedEnum ? $mode->value : $mode;
        $usesBands = $mode === PlanPremiumMode::AffiliateBands->value;
        $hasSubplans = $usesBands && (bool) ($state['has_subplans'] ?? false);

        $contracted = $plan->partnerAssignments()->whereNotNull('affiliates_count')->with('partnerCompany:id,legal_name')->get();

        if (! $usesBands && $contracted->isNotEmpty()) {
            return 'No se puede cambiar la modalidad: '.$this->partnerNames($contracted).' tiene(n) este plan contratado por cantidad de afiliados. Quite o ajuste esas asignaciones primero.';
        }

        $keptKeys = $hasSubplans
            ? collect($state['subplans'] ?? [])->filter(fn (mixed $item): bool => is_array($item))->pluck('key')->filter()->values()->all()
            : [];

        $removedInUse = $plan->subplans()
            ->whereNotIn('uuid', $keptKeys)
            ->whereHas('partnerAssignments')
            ->pluck('name');

        if ($removedInUse->isNotEmpty()) {
            return 'No se puede quitar '.$removedInUse->map(fn (string $name): string => "«{$name}»")->join(', ', ' ni ')
                .': hay aliados contratados con ese subplan. Cambie primero su subplan en «Compañías aliadas».';
        }

        return null;
    }

    /**
     * Guarda subplanes, tramos y precios. Solo aplica a planes por cantidad de afiliados;
     * para las demás modalidades la limpieza la hace {@see PlanMaintenance::normalizePremiumMode()}.
     *
     * @param  array<array-key, mixed>  $subplans
     * @param  array<array-key, mixed>  $bands
     */
    public function sync(Plan $plan, array $subplans, array $bands): void
    {
        if (! $plan->usesAffiliateBands()) {
            return;
        }

        DB::transaction(function () use ($plan, $subplans, $bands): void {
            $subplanIdsByKey = $plan->has_subplans ? $this->syncSubplans($plan, $subplans) : $this->removeAllSubplans($plan);

            $columns = $plan->has_subplans ? $subplanIdsByKey : [self::BASE_PRICE_KEY => null];
            $existingBands = $plan->affiliateBands()->with('prices')->get()->values();
            $keptBandIds = [];
            $sort = 0;

            foreach ($this->sortedBands($bands) as $item) {
                /** @var PlanAffiliateBand $band */
                $band = $existingBands->get($sort) ?? new PlanAffiliateBand(['plan_id' => $plan->getKey()]);
                $band->fill([
                    'min_affiliates' => (int) $item['min_affiliates'],
                    'max_affiliates' => is_numeric($item['max_affiliates'] ?? null) ? (int) $item['max_affiliates'] : null,
                    'sort' => $sort++,
                ]);
                $this->saveBandAvoidingUniqueClash($band, $existingBands->pluck('id')->all());
                $keptBandIds[] = $band->getKey();

                $prices = is_array($item['prices'] ?? null) ? $item['prices'] : [];
                $keptPriceIds = [];

                foreach ($columns as $key => $subplanId) {
                    $price = $prices[$key] ?? null;

                    if (! is_numeric($price)) {
                        continue;
                    }

                    $cell = $band->prices()->firstOrNew(['plan_subplan_id' => $subplanId]);
                    $cell->price_usd = round((float) $price, 2);
                    $cell->save();
                    $keptPriceIds[] = $cell->getKey();
                }

                $band->prices()->whereNotIn('id', $keptPriceIds)->get()->each(fn (PlanAffiliateBandPrice $cell) => $cell->delete());
            }

            $plan->affiliateBands()->whereNotIn('id', $keptBandIds)->get()->each(fn (PlanAffiliateBand $band) => $band->delete());

            $plan->unsetRelation('subplans')->unsetRelation('affiliateBands');
        });
    }

    /**
     * Tramos válidos ordenados por cantidad mínima de afiliados.
     *
     * @param  array<array-key, mixed>  $bands
     * @return list<array<string, mixed>>
     */
    private function sortedBands(array $bands): array
    {
        $valid = array_values(array_filter($bands, fn (mixed $item): bool => is_array($item) && is_numeric($item['min_affiliates'] ?? null)));
        usort($valid, fn (array $a, array $b): int => (int) $a['min_affiliates'] <=> (int) $b['min_affiliates']);

        return $valid;
    }

    /**
     * Guarda el tramo evitando el choque con la clave única (plan, mínimo) cuando los tramos
     * existentes se reacomodan: primero se aparta temporalmente el mínimo de los que se reutilizan.
     *
     * @param  list<int>  $existingIds
     */
    private function saveBandAvoidingUniqueClash(PlanAffiliateBand $band, array $existingIds): void
    {
        $clash = PlanAffiliateBand::query()
            ->where('plan_id', $band->plan_id)
            ->where('min_affiliates', $band->min_affiliates)
            ->when($band->exists, fn ($query) => $query->whereKeyNot($band->getKey()))
            ->first();

        if ($clash !== null && in_array($clash->getKey(), $existingIds, true)) {
            $clash->forceFill(['min_affiliates' => 4000000000 - $clash->getKey()])->saveQuietly();
        }

        $band->save();
    }

    /**
     * Copia subplanes (con servicios y catálogos), tramos y precios a otro plan.
     */
    public function copy(Plan $source, Plan $target): void
    {
        $state = $this->toFormState($source);

        $newKeys = [];
        foreach ($state['subplans'] as $key => $subplan) {
            $newKeys[$key] = (string) Str::uuid();
        }

        $subplans = [];
        foreach ($state['subplans'] as $key => $subplan) {
            $subplans[$newKeys[$key]] = [...$subplan, 'key' => $newKeys[$key]];
        }

        $bands = array_map(fn (array $band): array => [
            ...$band,
            'prices' => collect($band['prices'])->mapWithKeys(fn (mixed $price, string $key): array => [$newKeys[$key] ?? $key => $price])->all(),
        ], $state['affiliate_bands']);

        $this->sync($target, $subplans, $bands);
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return array<string, int> uuid => id
     */
    private function syncSubplans(Plan $plan, array $items): array
    {
        $idsByKey = [];
        $sort = 0;

        foreach ($items as $item) {
            if (! is_array($item) || blank($item['name'] ?? null)) {
                continue;
            }

            $key = filled($item['key'] ?? null) ? (string) $item['key'] : (string) Str::uuid();

            $subplan = $plan->subplans()->firstOrNew(['uuid' => $key]);
            $subplan->fill([
                'name' => trim((string) $item['name']),
                'description' => filled($item['description'] ?? null) ? (string) $item['description'] : null,
                'sort' => $sort++,
            ]);
            $subplan->save();

            $subplan->services()->sync($this->ids($item['services'] ?? []));
            $subplan->formularies()->sync($this->ids($item['formularies'] ?? []));

            $idsByKey[$key] = $subplan->getKey();
        }

        $this->deleteSubplansExcept($plan, array_values($idsByKey));

        return $idsByKey;
    }

    /**
     * @return array<string, int>
     */
    private function removeAllSubplans(Plan $plan): array
    {
        $this->deleteSubplansExcept($plan, []);

        return [];
    }

    /**
     * @param  list<int>  $keepIds
     */
    private function deleteSubplansExcept(Plan $plan, array $keepIds): void
    {
        $plan->subplans()->whereNotIn('id', $keepIds)->get()->each(function (PlanSubplan $subplan): void {
            if ($subplan->partnerAssignments()->exists()) {
                throw ValidationException::withMessages([
                    'data.subplans' => "No se puede quitar «{$subplan->name}»: hay aliados contratados con ese subplan.",
                ]);
            }

            $subplan->delete();
        });
    }

    /**
     * @return list<int>
     */
    private function ids(mixed $values): array
    {
        return collect(is_array($values) ? $values : [])
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  iterable<PartnerCompanyPlan>  $assignments
     */
    private function partnerNames(iterable $assignments): string
    {
        return collect($assignments)->map(fn (PartnerCompanyPlan $assignment): string => (string) $assignment->partnerCompany?->legal_name)->filter()->join(', ', ' y ');
    }
}
