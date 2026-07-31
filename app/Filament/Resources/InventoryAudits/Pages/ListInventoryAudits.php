<?php

namespace App\Filament\Resources\InventoryAudits\Pages;

use App\Filament\Resources\InventoryAudits\InventoryAuditResource;
use App\Models\Branch;
use App\Models\User;
use App\Services\Inventory\InventoryAuditApplyService;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ListInventoryAudits extends ListRecords
{
    protected static string $resource = InventoryAuditResource::class;

    protected static ?string $title = 'Auditorías de inventario';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openAudit')
                ->label('Abrir auditoría')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->modalHeading('Abrir auditoría de inventario')
                ->modalDescription('Se generará una línea por cada producto con inventario en la sucursal. Solo puede haber una auditoría abierta por sucursal.')
                ->modalSubmitActionLabel('Abrir')
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
                    Textarea::make('notes')
                        ->label('Notas (opcional)')
                        ->rows(2),
                    Checkbox::make('truncate_updates')
                        ->label('Limpiar reporte de productos actualizados de esta sucursal')
                        ->helperText('Vacía la tabla de actualizados de la sucursal antes de iniciar el nuevo ciclo.')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    try {
                        $audit = app(InventoryAuditApplyService::class)->open(
                            branchId: (int) $data['branch_id'],
                            actor: Auth::user(),
                            notes: $data['notes'] ?? null,
                            truncateBranchUpdates: (bool) ($data['truncate_updates'] ?? false),
                        );

                        Notification::make()
                            ->title('Auditoría abierta')
                            ->body('Puede comenzar a procesar productos.')
                            ->success()
                            ->send();

                        $this->redirect(InventoryAuditResource::getUrl('work', ['record' => $audit]));
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('No se pudo abrir')
                            ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
