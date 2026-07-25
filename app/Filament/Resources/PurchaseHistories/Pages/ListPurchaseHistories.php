<?php

namespace App\Filament\Resources\PurchaseHistories\Pages;

use App\Filament\Resources\PurchaseHistories\PurchaseHistoryResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\PurchaseFiscalHistoryBackfillSynchronizer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ListPurchaseHistories extends ListRecords
{
    protected static string $resource = PurchaseHistoryResource::class;

    protected static ?string $title = 'Histórico de compras';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Registro cronológico de compras al contado y de cada pago registrado contra cuentas por pagar. Use «Sincronizar histórico de compras» tras cargar el % SENIAT en cada proveedor.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncPurchaseFiscalHistory')
                ->label('Sincronizar histórico de compras')
                ->icon(Heroicon::ArrowPath)
                ->color('warning')
                ->modalWidth(Width::Large)
                ->modalHeading('Sincronizar histórico, retenciones y libro de compras')
                ->modalDescription(
                    'Recorre las compras existentes y genera lo faltante: histórico de contado, retenciones y filas del Libro de Compras. '
                    .'Solo crea retención si el proveedor ya tiene configurado el % SENIAT. '
                    .'Las compras cuyo proveedor aún no tiene % quedarán pendientes hasta que lo carguen y vuelvan a sincronizar.'
                )
                ->modalSubmitActionLabel('Ejecutar sincronización')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->canRunFiscalBackfill())
                ->action(function (): void {
                    $user = Auth::user();
                    AuditLogger::record(
                        event: 'purchase_fiscal_history_backfill_started',
                        description: 'Usuario inició sincronización masiva de histórico / retenciones / libro de compras.',
                        properties: [
                            'actor' => $user instanceof User ? ($user->email ?? $user->name) : null,
                        ],
                    );

                    $result = app(PurchaseFiscalHistoryBackfillSynchronizer::class)->run();

                    Notification::make()
                        ->title('Sincronización finalizada')
                        ->body($result->summaryBody())
                        ->success()
                        ->persistent()
                        ->send();

                    $this->redirect(static::getUrl(), navigate: true);
                }),
        ];
    }

    private function canRunFiscalBackfill(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && ($user->isAdministrator() || $user->hasGerenciaRole());
    }
}
