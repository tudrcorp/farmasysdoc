<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalPrinterMode;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cambia el modo de una máquina fiscal (caja por caja). Volver a «Desactivada» es inmediato y siempre
 * permitido; pasar a «Activa» exige la lista de chequeo salvo que un administrador lo fuerce.
 */
final class FiscalPrinterModeManager
{
    public function __construct(private FiscalPrinterActivationChecklist $checklist) {}

    public function change(FiscalPrinter $printer, FiscalPrinterMode $mode, string $actor, bool $force = false): FiscalPrinter
    {
        $previous = $printer->currentMode();

        if ($previous === $mode) {
            return $printer;
        }

        if ($mode !== FiscalPrinterMode::Disabled && $printer->physical_cash_box_id === null) {
            throw ValidationException::withMessages([
                'mode' => 'Asigne primero la caja del cajero a esta máquina fiscal.',
            ]);
        }

        if ($mode === FiscalPrinterMode::Active && ! $force && ! $this->checklist->canActivate($printer)) {
            throw ValidationException::withMessages([
                'mode' => 'No se puede activar todavía: '.implode(' · ', $this->checklist->failures($printer)),
            ]);
        }

        DB::transaction(function () use ($printer, $mode, $previous, $actor): void {
            if ($previous === FiscalPrinterMode::Simulation) {
                FiscalDocument::query()
                    ->where('fiscal_printer_id', $printer->id)
                    ->where('simulation', true)
                    ->where('is_test', false)
                    ->whereIn('status', [FiscalDocumentStatus::Pending, ...FiscalDocumentStatus::inFlight()])
                    ->update(['status' => FiscalDocumentStatus::Cancelled, 'resolved_by' => $actor, 'updated_at' => now()]);
            }

            $printer->forceFill([
                'mode' => $mode,
                'mode_changed_at' => now(),
                'mode_changed_by' => $actor,
            ])->save();
        });

        AuditLogger::record(
            'fiscal_printer_mode_changed',
            'Fiscal · '.$printer->name.' · '.$previous->label().' → '.$mode->label().($force ? ' (forzado)' : ''),
            FiscalPrinter::class,
            $printer->id,
            $printer->name,
            ['module' => 'fiscal', 'from' => $previous->value, 'to' => $mode->value, 'forced' => $force],
        );

        return $printer;
    }
}
