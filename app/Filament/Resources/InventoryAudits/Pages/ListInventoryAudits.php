<?php

namespace App\Filament\Resources\InventoryAudits\Pages;

use App\Filament\Resources\InventoryAudits\InventoryAuditResource;
use App\Models\Branch;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Inventory\InventoryAuditApplyService;
use App\Support\Filament\BranchAuthScope;
use App\Support\Inventory\InventoryAuditLetterRange;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
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
                ->modalDescription('Se generará una línea por cada producto con inventario que coincida con la sucursal, categoría y rango de letras. Solo puede haber una auditoría abierta por sucursal.')
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
                    Select::make('product_category_id')
                        ->label('Categoría de inventario')
                        ->options(fn (): array => ProductCategory::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->placeholder('Todas las categorías')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->helperText('Opcional. Limita la auditoría a una categoría.'),
                    Grid::make(2)
                        ->schema([
                            Select::make('letter_from')
                                ->label('Desde letra')
                                ->options(InventoryAuditLetterRange::options())
                                ->placeholder('Todas')
                                ->native(false)
                                ->requiredWith('letter_to')
                                ->helperText('Opcional. Primera letra del nombre del producto.'),
                            Select::make('letter_to')
                                ->label('Hasta letra')
                                ->options(InventoryAuditLetterRange::options())
                                ->placeholder('Todas')
                                ->native(false)
                                ->requiredWith('letter_from')
                                ->gte('letter_from')
                                ->helperText('Opcional. Última letra inclusive del rango.'),
                        ]),
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
                            productCategoryId: filled($data['product_category_id'] ?? null)
                                ? (int) $data['product_category_id']
                                : null,
                            letterFrom: $data['letter_from'] ?? null,
                            letterTo: $data['letter_to'] ?? null,
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
