<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalPrinterMode;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lado servidor del protocolo con el agente local de cada máquina fiscal.
 *
 * Reglas para no duplicar documentos fiscales (irreversibles):
 * - Una máquina procesa un documento a la vez, en orden de llegada.
 * - Si el agente se reinicia con un documento en curso, se le devuelve el mismo con
 *   `recovery = true`; el agente compara el contador fiscal con `printer_counter_before`
 *   antes de decidir si reimprime.
 * - Un resultado repetido con el mismo número fiscal es idempotente.
 */
final class FiscalAgentQueue
{
    public const OUTCOME_PRINTED = 'printed';

    public const OUTCOME_FAILED = 'failed';

    public const OUTCOME_UNCERTAIN = 'uncertain';

    public const OUTCOME_SIMULATED = 'simulated';

    public const CAPABILITY_SIMULATION = 'simulation';

    public const CAPABILITY_TEST_LAB = 'test_lab';

    /**
     * Espera (long-poll) hasta que haya un documento para la máquina o venza el plazo.
     *
     * @param  list<string>  $capabilities
     * @return array{document: FiscalDocument, recovery: bool}|null
     */
    public function waitForNext(FiscalPrinter $printer, int $waitSeconds, array $capabilities = []): ?array
    {
        $waitSeconds = max(0, min($waitSeconds, (int) config('fiscal.agent.max_wait_seconds', 15)));
        $intervalMicros = max(100, (int) config('fiscal.agent.poll_interval_ms', 500)) * 1000;
        $deadline = microtime(true) + $waitSeconds;

        do {
            $printer->refresh();
            $next = $this->claimNext($printer, $capabilities);

            if ($next !== null || microtime(true) >= $deadline) {
                return $next;
            }

            usleep($intervalMicros);
        } while (true);
    }

    /**
     * Qué se entrega según el modo de la máquina:
     * - Documentos reales en curso: siempre (hay que cerrarlos aunque se haya cambiado el modo).
     * - Documentos reales pendientes de ventas: solo en modo «Activa».
     * - Simulaciones de ventas: solo en modo «Simulación» y si el agente declara la capacidad (un agente antiguo las imprimiría).
     * - Pruebas del laboratorio: en cualquier modo (las pide un administrador) y solo a agentes con la capacidad «test_lab».
     *
     * @param  list<string>  $capabilities
     * @return array{document: FiscalDocument, recovery: bool}|null
     */
    public function claimNext(FiscalPrinter $printer, array $capabilities = []): ?array
    {
        $mode = $printer->currentMode();
        $canSimulate = in_array(self::CAPABILITY_SIMULATION, $capabilities, true);
        $canTest = in_array(self::CAPABILITY_TEST_LAB, $capabilities, true);

        return DB::transaction(function () use ($printer, $mode, $canSimulate, $canTest): ?array {
            $inFlight = FiscalDocument::query()
                ->where('fiscal_printer_id', $printer->id)
                ->whereIn('status', FiscalDocumentStatus::inFlight())
                ->where(function (Builder $query) use ($canSimulate): void {
                    $query->where('simulation', false);

                    if ($canSimulate) {
                        $query->orWhere('simulation', true);
                    }
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($inFlight instanceof FiscalDocument) {
                return ['document' => $inFlight, 'recovery' => true];
            }

            $pending = FiscalDocument::query()
                ->where('fiscal_printer_id', $printer->id)
                ->where('status', FiscalDocumentStatus::Pending)
                ->where(function (Builder $query) use ($mode, $canSimulate, $canTest): void {
                    $query->whereRaw('1 = 0');

                    if ($mode === FiscalPrinterMode::Active) {
                        $query->orWhere(fn (Builder $q) => $q->where('is_test', false)->where('simulation', false));
                    }

                    if ($mode === FiscalPrinterMode::Simulation && $canSimulate) {
                        $query->orWhere(fn (Builder $q) => $q->where('is_test', false)->where('simulation', true));
                    }

                    if ($canTest) {
                        $query->orWhere(fn (Builder $q) => $q->where('is_test', true)->where('simulation', false));
                    }

                    if ($canTest && $canSimulate) {
                        $query->orWhere(fn (Builder $q) => $q->where('is_test', true)->where('simulation', true));
                    }
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $pending instanceof FiscalDocument) {
                return null;
            }

            $pending->forceFill([
                'status' => FiscalDocumentStatus::Claimed,
                'claimed_at' => now(),
                'attempts' => $pending->attempts + 1,
            ])->save();

            return ['document' => $pending, 'recovery' => false];
        });
    }

    /**
     * El agente avisa que va a enviar el documento a la máquina, con el último número fiscal leído antes.
     */
    public function markStarted(FiscalDocument $document, ?string $counterBefore): FiscalDocument
    {
        return DB::transaction(function () use ($document, $counterBefore): FiscalDocument {
            $locked = FiscalDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, FiscalDocumentStatus::inFlight(), true)) {
                throw new FiscalAgentConflictException('El documento no está tomado por el agente (estado: '.$locked->status->label().').');
            }

            if ($locked->simulation) {
                throw new FiscalAgentConflictException('Los documentos de simulación no se imprimen.');
            }

            if ($locked->status === FiscalDocumentStatus::Printing) {
                return $locked;
            }

            $locked->forceFill([
                'status' => FiscalDocumentStatus::Printing,
                'started_at' => now(),
                'printer_counter_before' => $counterBefore,
            ])->save();

            return $locked;
        });
    }

    /**
     * @param  array{outcome: string, fiscal_number?: ?string, printer_serial?: ?string, z_number?: ?string, printer_datetime?: ?string, printer_total_ves?: float|string|null, printer_computes_igtf?: ?bool, error_code?: ?string, error_message?: ?string, raw?: ?array<string, mixed>}  $result
     */
    public function applyResult(FiscalDocument $document, array $result): FiscalDocument
    {
        $document = DB::transaction(function () use ($document, $result): FiscalDocument {
            $locked = FiscalDocument::query()
                ->with('fiscalPrinter')
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $outcome = $result['outcome'];
            $fiscalNumber = filled($result['fiscal_number'] ?? null) ? (string) $result['fiscal_number'] : null;

            if ($locked->status === FiscalDocumentStatus::Simulated && $outcome === self::OUTCOME_SIMULATED) {
                return $locked;
            }

            if ($outcome === self::OUTCOME_SIMULATED && ! $locked->simulation) {
                throw new FiscalAgentConflictException('Un documento real no puede cerrarse como simulación.');
            }

            if ($locked->status === FiscalDocumentStatus::Printed) {
                if ($outcome === self::OUTCOME_PRINTED && $fiscalNumber === $locked->fiscal_number) {
                    return $locked;
                }

                throw new FiscalAgentConflictException('El documento ya figura impreso con el número fiscal '.$locked->fiscal_number.'.');
            }

            if (! in_array($locked->status, FiscalDocumentStatus::inFlight(), true)) {
                throw new FiscalAgentConflictException('El documento no está tomado por el agente (estado: '.$locked->status->label().').');
            }

            $common = [
                'printer_serial' => $result['printer_serial'] ?? $locked->printer_serial,
                'raw_response' => $result['raw'] ?? null,
                'error_code' => $result['error_code'] ?? null,
                'error_message' => $result['error_message'] ?? null,
            ];

            if ($locked->simulation) {
                // Una simulación nunca debe imprimir; si el agente dice lo contrario, queda para revisión humana.
                $locked->forceFill(array_merge($common, $outcome === self::OUTCOME_SIMULATED
                    ? ['status' => FiscalDocumentStatus::Simulated, 'printed_at' => now()]
                    : [
                        'status' => FiscalDocumentStatus::NeedsReview,
                        'fiscal_number' => $fiscalNumber,
                        'error_code' => 'simulation_not_simulated',
                        'error_message' => 'El agente reportó «'.$outcome.'» en un documento de simulación. Verifique la máquina fiscal.',
                    ]))->save();

                return $locked;
            }

            if ($outcome === self::OUTCOME_PRINTED) {
                $printerTotal = isset($result['printer_total_ves']) && $result['printer_total_ves'] !== null
                    ? round((float) $result['printer_total_ves'], 2)
                    : null;

                $expectedTotal = $this->expectedPrinterTotal($locked, $result['printer_computes_igtf'] ?? null);
                $missingFiscalNumber = $locked->type->requiresFiscalNumber() && $fiscalNumber === null;
                $totalsMatch = $this->totalsMatch($locked, $expectedTotal, $printerTotal);

                $status = $totalsMatch && ! $missingFiscalNumber
                    ? FiscalDocumentStatus::Printed
                    : FiscalDocumentStatus::NeedsReview;

                $locked->forceFill(array_merge($common, [
                    'status' => $status,
                    'fiscal_number' => $fiscalNumber,
                    'z_number' => $result['z_number'] ?? null,
                    'printer_datetime' => filled($result['printer_datetime'] ?? null)
                        ? Carbon::parse((string) $result['printer_datetime'])
                        : null,
                    'printer_total_ves' => $printerTotal,
                    'printed_at' => now(),
                ]))->save();

                if ($missingFiscalNumber) {
                    $locked->forceFill([
                        'error_code' => 'missing_fiscal_number',
                        'error_message' => 'El agente reportó el documento como impreso pero sin número fiscal.',
                    ])->save();
                } elseif (! $totalsMatch) {
                    $locked->forceFill([
                        'error_code' => 'total_mismatch',
                        'error_message' => 'Total de la máquina fiscal ('.number_format((float) $printerTotal, 2, ',', '.')
                            .') distinto al esperado ('.number_format((float) $expectedTotal, 2, ',', '.').').',
                    ])->save();
                }

                $this->rememberPrinterCounters($locked);

                return $locked;
            }

            $locked->forceFill(array_merge($common, [
                'status' => $outcome === self::OUTCOME_FAILED
                    ? FiscalDocumentStatus::Failed
                    : FiscalDocumentStatus::NeedsReview,
            ]))->save();

            return $locked;
        });

        if ($document->status === FiscalDocumentStatus::Simulated) {
            return $document;
        }

        if ($document->is_test) {
            app(FiscalTestLab::class)->queueAutoCreditNote($document);
        }

        AuditLogger::record(
            'fiscal_document_'.$document->status->value,
            'Fiscal · '.$document->type->label().' · '.$document->status->label()
                .($document->fiscal_number ? ' · Nº '.$document->fiscal_number : ''),
            FiscalDocument::class,
            $document->id,
            $document->fiscal_number ?? $document->uuid,
            [
                'module' => 'fiscal',
                'sale_id' => $document->sale_id,
                'fiscal_printer_id' => $document->fiscal_printer_id,
                'error_code' => $document->error_code,
            ],
        );

        return $document;
    }

    /**
     * @param  array{status?: ?array<string, mixed>, agent_version?: ?string, last_fiscal_number?: ?string, last_z_number?: ?string}  $data
     */
    public function recordHeartbeat(FiscalPrinter $printer, array $data): void
    {
        $printer->forceFill([
            'last_heartbeat_at' => now(),
            'last_status' => $data['status'] ?? $printer->last_status,
            'agent_version' => $data['agent_version'] ?? $printer->agent_version,
            'last_fiscal_number' => $data['last_fiscal_number'] ?? $printer->last_fiscal_number,
            'last_z_number' => $data['last_z_number'] ?? $printer->last_z_number,
        ])->saveQuietly();
    }

    /**
     * Total que debería cobrar la máquina: si no tiene IGTF programado, el IGTF de la venta no forma parte del documento fiscal.
     */
    private function expectedPrinterTotal(FiscalDocument $document, mixed $printerComputesIgtf): ?float
    {
        if ($document->expected_total_ves === null) {
            return null;
        }

        $expected = (float) $document->expected_total_ves;

        if ($printerComputesIgtf === false || $printerComputesIgtf === 0 || $printerComputesIgtf === '0') {
            $payload = is_array($document->payload) ? $document->payload : [];
            $expected -= (float) ($payload['expected']['igtf_ves'] ?? 0);
        }

        return round($expected, 2);
    }

    private function totalsMatch(FiscalDocument $document, ?float $expectedTotal, ?float $printerTotal): bool
    {
        if (! $document->type->requiresFiscalNumber() || $expectedTotal === null || $printerTotal === null) {
            return true;
        }

        $tolerance = max(0.0, (float) config('fiscal.printers.total_tolerance_ves', 0.05));

        return abs($printerTotal - $expectedTotal) <= $tolerance + 0.000001;
    }

    private function rememberPrinterCounters(FiscalDocument $document): void
    {
        $printer = $document->fiscalPrinter;

        if (! $printer instanceof FiscalPrinter) {
            return;
        }

        $updates = [];

        if ($document->type === FiscalDocumentType::Invoice && filled($document->fiscal_number)) {
            $updates['last_fiscal_number'] = $document->fiscal_number;
        }

        if ($document->type === FiscalDocumentType::ZReport && filled($document->z_number ?? $document->fiscal_number)) {
            $updates['last_z_number'] = $document->z_number ?? $document->fiscal_number;
        }

        if ($updates !== []) {
            $printer->forceFill($updates)->saveQuietly();
        }
    }
}
