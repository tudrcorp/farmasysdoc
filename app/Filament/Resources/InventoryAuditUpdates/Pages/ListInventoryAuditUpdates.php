<?php

namespace App\Filament\Resources\InventoryAuditUpdates\Pages;

use App\Filament\Resources\InventoryAuditUpdates\InventoryAuditUpdateResource;
use App\Models\Branch;
use App\Models\User;
use App\Services\Inventory\InventoryAuditApplyService;
use App\Services\Inventory\InventoryAuditUpdateCsvExporter;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListInventoryAuditUpdates extends ListRecords
{
    protected static string $resource = InventoryAuditUpdateResource::class;

    protected static ?string $title = 'Productos actualizados (auditoría)';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Detalle de productos corregidos en auditorías. Puede descargar CSV o truncar el reporte por sucursal para un nuevo ciclo.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Descargar CSV')
                ->icon(Heroicon::ArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => app(InventoryAuditUpdateCsvExporter::class)
                    ->stream($this->getTableQueryForExport()))
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                ]),
            Action::make('truncate')
                ->label('Truncar reporte')
                ->icon(Heroicon::Trash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Truncar productos actualizados')
                ->modalDescription('Eliminará permanentemente las filas del reporte de la sucursal seleccionada. El historial de sesiones/líneas no se borra.')
                ->modalSubmitActionLabel('Sí, truncar')
                ->form([
                    Select::make('branch_id')
                        ->label('Sucursal')
                        ->options(fn (): array => BranchAuthScope::applyToBranchFormSelect(
                            Branch::query()->where('is_active', true)->orderBy('name'),
                        )->pluck('name', 'id')->all())
                        ->default(function (): ?int {
                            $user = Auth::user();
                            if (! $user instanceof User) {
                                return null;
                            }

                            $ids = $user->isAdministrator()
                                ? []
                                : $user->restrictedBranchIdsForQueries();

                            if (count($ids) === 1) {
                                return $ids[0];
                            }

                            return filled($user->branch_id) ? (int) $user->branch_id : null;
                        })
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    try {
                        $deleted = app(InventoryAuditApplyService::class)->truncateUpdatesForBranch(
                            (int) $data['branch_id'],
                            Auth::user(),
                        );

                        Notification::make()
                            ->title('Reporte truncado')
                            ->body($deleted.' registro(s) eliminado(s).')
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('No se pudo truncar')
                            ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
