<?php

namespace App\Services\Partners;

use App\Enums\PartnerAffiliateSource;
use App\Enums\PartnerPanelPermission;
use App\Enums\PlanPremiumMode;
use App\Enums\PlanStatus;
use App\Models\Client;
use App\Models\PartnerCompanyPlan;
use App\Models\PartnerPlanAffiliate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Afiliados de los planes de los aliados: alta en mostrador (el cajero validó con el aliado),
 * carga masiva desde Excel/CSV al activar el convenio, y consulta de los convenios Flexible con
 * los que un cliente puede comprar en caja.
 */
class PartnerAffiliateService
{
    public const IMPORT_MAX_ROWS = 20000;

    /**
     * Asignaciones de planes Flexible (fondo de consumo) activos de aliados activos.
     *
     * @return Builder<PartnerCompanyPlan>
     */
    public static function flexibleAssignmentsQuery(): Builder
    {
        return PartnerCompanyPlan::query()
            ->whereHas('plan', fn (Builder $query) => $query
                ->where('premium_mode', PlanPremiumMode::ConsumptionFund->value)
                ->where('status', PlanStatus::Active->value))
            ->whereHas('partnerCompany', fn (Builder $query) => $query->where('is_active', true));
    }

    /**
     * Afiliaciones activas del cliente a planes Flexible vigentes, con el disponible de cada fondo.
     *
     * @return list<array{affiliate_id: int, assignment_id: int, partner: string, plan: string, available: float}>
     */
    public function flexibleOptionsForClient(int $clientId): array
    {
        $assignmentIds = self::flexibleAssignmentsQuery()->pluck('id');

        return PartnerPlanAffiliate::query()
            ->active()
            ->where('client_id', $clientId)
            ->whereIn('partner_company_plan_id', $assignmentIds)
            ->with(['assignment.partnerCompany:id,legal_name,trade_name', 'assignment.plan:id,name'])
            ->get()
            ->map(fn (PartnerPlanAffiliate $affiliate): array => [
                'affiliate_id' => (int) $affiliate->getKey(),
                'assignment_id' => (int) $affiliate->partner_company_plan_id,
                'partner' => (string) ($affiliate->assignment?->partnerCompany?->trade_name ?: $affiliate->assignment?->partnerCompany?->legal_name),
                'plan' => (string) $affiliate->assignment?->plan?->name,
                'available' => $affiliate->assignment?->fundAvailableUsd() ?? 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * Opciones de planes Flexible para afiliar en mostrador: «Aliado · Plan».
     *
     * @return array<int, string>
     */
    public function flexibleAssignmentOptions(): array
    {
        return self::flexibleAssignmentsQuery()
            ->with(['partnerCompany:id,legal_name,trade_name', 'plan:id,name'])
            ->get()
            ->sortBy(fn (PartnerCompanyPlan $assignment): string => Str::lower((string) $assignment->partnerCompany?->legal_name))
            ->mapWithKeys(fn (PartnerCompanyPlan $assignment): array => [
                (int) $assignment->getKey() => ($assignment->partnerCompany?->trade_name ?: $assignment->partnerCompany?->legal_name).' · '.$assignment->plan?->name,
            ])
            ->all();
    }

    /**
     * Afilia (o reactiva) a un cliente en una asignación. Idempotente.
     */
    public function enroll(
        PartnerCompanyPlan $assignment,
        Client $client,
        PartnerAffiliateSource $source,
        ?User $actor = null,
        ?string $validatedWith = null,
        ?int $branchId = null,
    ): PartnerPlanAffiliate {
        if ($source === PartnerAffiliateSource::Counter && blank($validatedWith)) {
            throw ValidationException::withMessages(['validated_with' => 'Indique con quién validó la afiliación en la empresa.']);
        }

        $affiliate = PartnerPlanAffiliate::query()->firstOrNew([
            'partner_company_plan_id' => $assignment->getKey(),
            'client_id' => $client->getKey(),
        ]);

        if ($affiliate->exists && $affiliate->is_active) {
            return $affiliate;
        }

        $wasInactive = $affiliate->exists;

        $affiliate->fill([
            'partner_company_id' => $assignment->partner_company_id,
            'plan_id' => $assignment->plan_id,
            'is_active' => true,
            'source' => $affiliate->exists ? $affiliate->source : $source,
            'validated_with' => filled($validatedWith) ? trim((string) $validatedWith) : $affiliate->validated_with,
            'enrolled_by' => $actor?->getKey() ?? $affiliate->enrolled_by,
            'branch_id' => $branchId ?? $affiliate->branch_id,
            'enrolled_at' => now(),
            'deactivated_at' => null,
            'deactivated_by' => null,
            'deactivated_via' => null,
            'deactivation_reason' => null,
        ])->save();

        if ($source === PartnerAffiliateSource::Counter || $wasInactive) {
            PartnerPanelActivityRecorder::record(
                (int) $assignment->partner_company_id,
                $wasInactive ? 'affiliate_reactivated' : 'affiliate_enrolled',
                ($wasInactive ? 'Reincorporó a ' : 'Afilió a ').$client->name.' (C.I. '.$client->document_number.')'
                    .($source === PartnerAffiliateSource::Counter && filled($validatedWith) ? ' en mostrador. Validado con: '.trim((string) $validatedWith) : ''),
                $actor,
                $affiliate,
                (string) $client->name,
            );
        }

        return $affiliate;
    }

    /**
     * Da de baja o reincorpora a un afiliado. Desde el panel del aliado exige el permiso «Dar de
     * baja afiliados» en la misma empresa; la baja siempre exige motivo. Queda en la bitácora.
     */
    public function setActive(PartnerPlanAffiliate $affiliate, bool $active, ?User $actor = null, ?string $reason = null): void
    {
        if ($actor?->isPartnerCompanyUser()) {
            if (! $actor->hasPartnerPermission(PartnerPanelPermission::DeactivateAffiliates) || (int) $actor->partner_company_id !== (int) $affiliate->partner_company_id) {
                throw ValidationException::withMessages(['affiliate' => 'No tiene permiso para dar de baja afiliados de este convenio.']);
            }
        }

        if (! $active && blank($reason) && $actor !== null) {
            throw ValidationException::withMessages(['reason' => 'Indique el motivo de la baja.']);
        }

        if ((bool) $affiliate->is_active === $active) {
            return;
        }

        $affiliate->forceFill([
            'is_active' => $active,
            'deactivated_at' => $active ? null : now(),
            'deactivated_by' => $active ? null : $actor?->getKey(),
            'deactivated_via' => $active ? null : ($actor?->isPartnerCompanyUser() ? 'aliado' : 'farmadoc'),
            'deactivation_reason' => $active ? null : (filled($reason) ? mb_substr(trim((string) $reason), 0, 500) : null),
        ])->save();

        $affiliate->loadMissing(['client:id,name,document_number', 'plan:id,name']);
        $label = (string) $affiliate->client?->name.' (C.I. '.$affiliate->client?->document_number.')';

        PartnerPanelActivityRecorder::record(
            (int) $affiliate->partner_company_id,
            $active ? 'affiliate_reactivated' : 'affiliate_deactivated',
            ($active ? 'Reincorporó a ' : 'Dio de baja a ').$label.' del plan '.$affiliate->plan?->name.($active ? '' : (filled($reason) ? '. Motivo: '.trim((string) $reason) : '')),
            $actor,
            $affiliate,
            $label,
            $active ? [] : ['reason' => $reason],
        );
    }

    /**
     * Lee un Excel/CSV con columnas cédula, nombre, teléfono y correo (encabezados en la fila 1,
     * en cualquier orden). Devuelve filas válidas y errores por fila, sin guardar nada.
     *
     * @return array{rows: list<array{row: int, document: string, name: string, phone: ?string, email: ?string}>, errors: list<string>, duplicates: int}
     */
    public function parseImportFile(string $path): array
    {
        $data = $this->readRows($path);
        $header = array_map(fn (mixed $cell): string => Str::of((string) $cell)->lower()->ascii()->replaceMatches('/[^a-z]/', '')->toString(), $data[0] ?? []);

        $columns = [
            'document' => $this->findColumn($header, ['cedula', 'ci', 'documento', 'doc', 'cedulaidentidad', 'numerodocumento', 'rif']),
            'name' => $this->findColumn($header, ['nombre', 'nombres', 'nombrecompleto', 'afiliado', 'cliente']),
            'phone' => $this->findColumn($header, ['telefono', 'celular', 'movil', 'tlf', 'whatsapp']),
            'email' => $this->findColumn($header, ['correo', 'email', 'correoelectronico', 'mail']),
        ];

        if ($columns['document'] === null || $columns['name'] === null) {
            throw ValidationException::withMessages(['file' => 'El archivo debe tener en la primera fila las columnas «Cédula» y «Nombre» (opcionales: «Teléfono» y «Correo»).']);
        }

        if (count($data) - 1 > self::IMPORT_MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'El archivo supera '.number_format(self::IMPORT_MAX_ROWS, 0, ',', '.').' filas. Divídalo en varios archivos.']);
        }

        $rows = [];
        $errors = [];
        $seen = [];
        $duplicates = 0;

        foreach (array_slice($data, 1, preserve_keys: true) as $index => $row) {
            $line = $index + 1;
            $document = self::normalizeDocument((string) ($row[$columns['document']] ?? ''));
            $name = trim((string) ($row[$columns['name']] ?? ''));

            if ($document === '' && $name === '') {
                continue;
            }

            if ($document === '' || strlen($document) < 5) {
                $errors[] = "Fila {$line}: cédula vacía o inválida.";

                continue;
            }

            if ($name === '') {
                $errors[] = "Fila {$line}: falta el nombre.";

                continue;
            }

            if (isset($seen[$document])) {
                $duplicates++;

                continue;
            }

            $seen[$document] = true;
            $email = $columns['email'] !== null ? trim((string) ($row[$columns['email']] ?? '')) : '';
            $phone = $columns['phone'] !== null ? trim((string) ($row[$columns['phone']] ?? '')) : '';

            $rows[] = [
                'row' => $line,
                'document' => $document,
                'name' => Str::of($name)->squish()->title()->toString(),
                'phone' => $phone !== '' ? $phone : null,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? Str::lower($email) : null,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors, 'duplicates' => $duplicates];
    }

    /**
     * Guarda la carga: crea los clientes que no existen (por cédula) y los afilia.
     *
     * @param  list<array{row: int, document: string, name: string, phone: ?string, email: ?string}>  $rows
     * @return array{enrolled: int, already: int, created_clients: int}
     */
    public function import(PartnerCompanyPlan $assignment, array $rows, User $actor): array
    {
        $result = ['enrolled' => 0, 'already' => 0, 'created_clients' => 0];
        $actorLabel = (string) ($actor->email ?: $actor->name);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::transaction(function () use ($assignment, $chunk, $actor, $actorLabel, &$result): void {
                $documents = array_column($chunk, 'document');
                $clients = Client::query()->whereIn('document_number', $documents)->get()->keyBy('document_number');
                $existing = PartnerPlanAffiliate::query()
                    ->where('partner_company_plan_id', $assignment->getKey())
                    ->whereIn('client_id', $clients->modelKeys())
                    ->where('is_active', true)
                    ->pluck('client_id')
                    ->flip();

                foreach ($chunk as $row) {
                    $client = $clients->get($row['document']);

                    if (! $client instanceof Client) {
                        $client = Client::query()->create([
                            'name' => $row['name'],
                            'document_type' => 'CC',
                            'document_number' => $row['document'],
                            'email' => $row['email'] ?? 'convenio+'.Str::uuid()->toString().'@afiliados.invalid',
                            'phone' => $row['phone'] ?? '—',
                            'address' => '—',
                            'city' => '—',
                            'state' => '—',
                            'country' => 'VENEZUELA',
                            'status' => 'active',
                            'customer_discount' => 0,
                            'created_by' => $actorLabel,
                            'updated_by' => $actorLabel,
                        ]);
                        $clients->put($row['document'], $client);
                        $result['created_clients']++;
                    }

                    if ($existing->has($client->getKey())) {
                        $result['already']++;

                        continue;
                    }

                    $this->enroll($assignment, $client, PartnerAffiliateSource::Import, $actor);
                    $result['enrolled']++;
                }
            });
        }

        PartnerPanelActivityRecorder::record(
            (int) $assignment->partner_company_id,
            'affiliates_imported',
            'Cargó afiliados desde archivo: '.$result['enrolled'].' nuevos, '.$result['already'].' ya estaban.',
            $actor,
            $assignment,
            null,
            $result,
        );

        return $result;
    }

    /**
     * Filas del archivo (XLSX: primera hoja; CSV: separador «,», «;» o tabulador detectado).
     *
     * @return list<list<mixed>>
     */
    private function readRows(string $path): array
    {
        $isXlsx = $this->looksLikeXlsx($path);

        try {
            if ($isXlsx) {
                $reader = new XlsxReader;
            } else {
                $options = new CsvOptions;
                $options->FIELD_DELIMITER = $this->detectCsvDelimiter($path);
                $reader = new CsvReader($options);
            }

            $reader->open($path);
            $rows = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();

                    if (count($rows) > self::IMPORT_MAX_ROWS + 1) {
                        break;
                    }
                }

                break;
            }

            $reader->close();
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'No se pudo leer el archivo. Use Excel (.xlsx) o CSV.']);
        }

        if ($rows !== [] && is_string($rows[0][0] ?? null)) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
        }

        return $rows;
    }

    private function looksLikeXlsx(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $signature = (string) fread($handle, 4);
        fclose($handle);

        return $signature === "PK\x03\x04";
    }

    private function detectCsvDelimiter(string $path): string
    {
        $handle = @fopen($path, 'rb');
        $line = $handle !== false ? (string) fgets($handle) : '';

        if ($handle !== false) {
            fclose($handle);
        }

        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * Cédula solo con dígitos (como la guarda la caja).
     */
    public static function normalizeDocument(string $document): string
    {
        return preg_replace('/\D+/', '', $document) ?? '';
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $aliases
     */
    private function findColumn(array $header, array $aliases): ?int
    {
        foreach ($header as $index => $name) {
            if (in_array($name, $aliases, true)) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, PartnerPlanAffiliate>
     */
    public function affiliatesOf(PartnerCompanyPlan $assignment): Collection
    {
        return $assignment->affiliates()->with('client')->get();
    }
}
