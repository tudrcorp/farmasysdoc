<?php

namespace App\Services\WhatsappAgent;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Finance\BcvRate;
use App\Support\Sales\ProductUnitPricingForBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Búsqueda de productos para el asistente: información del producto, existencia en cada sucursal
 * y precio de cada sucursal en USD y en Bs. (tasa BCV del día), ya calculado para que la IA no haga cuentas.
 *
 * La existencia se expresa como «disponible» / «quedan pocas unidades», nunca en cantidades.
 */
final class WhatsappProductAvailabilitySearch
{
    public const LOW_STOCK_THRESHOLD = 5;

    private ?float $rate = null;

    private bool $rateResolved = false;

    public function __construct(private readonly VenezuelaOfficialUsdVesRateClient $rateClient) {}

    /**
     * @return array{bcv_rate: float|null, results: list<array<string, mixed>>}
     */
    public function search(string $term, int $limit = 5): array
    {
        $term = trim(mb_substr($term, 0, 80));

        if (mb_strlen($term) < 2) {
            return ['bcv_rate' => $this->rate(), 'results' => []];
        }

        $products = $this->matchingProducts($term, max(1, min(10, $limit)));
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'city', 'address']);

        $results = $products
            ->map(fn (Product $product): array => $this->present($product, $branches))
            ->sortByDesc(fn (array $row): int => $row['available_anywhere'] ? 1 : 0)
            ->values()
            ->all();

        return ['bcv_rate' => $this->rate(), 'results' => $results];
    }

    /**
     * @return Collection<int, Product>
     */
    private function matchingProducts(string $term, int $limit): Collection
    {
        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $term, 5, PREG_SPLIT_NO_EMPTY) ?: [],
            static fn (string $token): bool => mb_strlen($token) >= 2,
        ));

        $query = Product::query()
            ->where('is_active', true)
            ->with(['productCategory:id,name', 'inventories']);

        if (preg_match('/^\d{6,}$/', $term) === 1) {
            $query->where('barcode', $term);
        } else {
            foreach ($tokens as $token) {
                $like = '%'.addcslashes($token, '%_\\').'%';

                $query->where(function (Builder $where) use ($like): void {
                    $where->where('name', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('active_ingredient', 'like', $like)
                        ->orWhere('concentration', 'like', $like);
                });
            }

            $query->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [addcslashes($term, '%_\\').'%']);
        }

        return $query->orderBy('name')->limit($limit)->get();
    }

    /**
     * @param  Collection<int, Branch>  $branches
     * @return array<string, mixed>
     */
    private function present(Product $product, Collection $branches): array
    {
        $inventories = $product->inventories->keyBy('branch_id');
        $availability = [];

        foreach ($branches as $branch) {
            $inventory = $inventories->get($branch->getKey());

            if (! $inventory instanceof Inventory || $inventory->available_quantity <= 0) {
                continue;
            }

            $pricing = ProductUnitPricingForBranch::resolve($product, (int) $branch->getKey(), $inventory);
            $usd = round((float) $pricing['unit_final'], 2);

            $availability[] = [
                'branch_id' => (int) $branch->getKey(),
                'branch_name' => (string) $branch->name,
                'branch_city' => $branch->city,
                'branch_address' => $branch->address,
                'stock' => $inventory->available_quantity <= self::LOW_STOCK_THRESHOLD ? 'quedan pocas unidades' : 'disponible',
                'price_usd' => $usd,
                'price_ves' => $this->ves($usd),
                'price_text' => $this->priceText($usd),
            ];
        }

        $activeIngredient = $product->active_ingredient;

        return [
            'product_id' => (int) $product->getKey(),
            'name' => (string) $product->name,
            'brand' => $product->brand,
            'laboratory' => $product->manufacturer,
            'active_ingredient' => is_array($activeIngredient) ? implode(', ', array_filter($activeIngredient, 'is_string')) : $activeIngredient,
            'concentration' => $product->concentration,
            'presentation' => $product->presentation_type,
            'category' => $product->productCategory?->name,
            'description' => filled($product->description) ? mb_strimwidth(strip_tags((string) $product->description), 0, 500, '…') : null,
            'requires_prescription' => (bool) $product->requires_prescription,
            'is_controlled_substance' => (bool) $product->is_controlled_substance,
            'storage_conditions' => $product->storage_conditions,
            'available_anywhere' => $availability !== [],
            'availability_by_branch' => $availability,
        ];
    }

    private function priceText(float $usd): string
    {
        $text = '$'.number_format($usd, 2, ',', '.');
        $ves = $this->ves($usd);

        return $ves !== null ? $text.' (Bs. '.number_format($ves, 2, ',', '.').' a tasa BCV de hoy)' : $text;
    }

    private function ves(float $usd): ?float
    {
        $rate = $this->rate();

        return $rate !== null ? round($usd * $rate, 2) : null;
    }

    private function rate(): ?float
    {
        if (! $this->rateResolved) {
            try {
                $this->rate = BcvRate::truncateOrNull($this->rateClient->rateForDate(now()));
            } catch (Throwable) {
                $this->rate = null;
            }

            $this->rateResolved = true;
        }

        return $this->rate;
    }
}
