<?php

namespace App\Filament\Resources\Purchases\Actions;

use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

final class CompleteSupplierSeniatRetentionAction
{
    /**
     * Tras guardar la retención en el proveedor, se invoca para continuar el flujo de la compra.
     *
     * @param  callable(): void  $onCompleted
     */
    public static function make(callable $onCompleted): Action
    {
        return Action::make('completeSupplierSeniatRetention')
            ->label('')
            ->icon(Heroicon::Calculator)
            // No usar ->hidden(true): Filament trata acciones ocultas como deshabilitadas y mountAction() no abre el modal.
            ->extraAttributes([
                'class' => 'hidden',
            ])
            ->modalWidth(Width::Large)
            ->modalHeading('Retención SENIAT del proveedor')
            ->modalDescription('Este proveedor no tiene cargado el porcentaje de retención. Indíquelo para continuar con el registro de la compra sin salir del proceso.')
            ->modalSubmitActionLabel('Guardar retención y continuar')
            ->modalCancelActionLabel('Cerrar')
            ->modalIcon(Heroicon::Calculator)
            ->fillForm(function (Action $action): array {
                $supplierId = (int) ($action->getArguments()['supplier_id'] ?? 0);
                $supplier = $supplierId > 0
                    ? Supplier::query()->find($supplierId)
                    : null;

                return [
                    'supplier_label' => $supplier !== null
                        ? trim(($supplier->tax_id ? $supplier->tax_id.' — ' : '').$supplier->displayName())
                        : '',
                    'seniat_retention_percent' => $supplier?->seniat_retention_percent,
                ];
            })
            ->schema([
                Section::make()
                    ->heading('Datos del proveedor')
                    ->description('Se actualizará la ficha del proveedor con el porcentaje indicado.')
                    ->icon(Heroicon::BuildingOffice2)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('supplier_label')
                                    ->label('Proveedor')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->prefixIcon(Heroicon::Truck),
                                TextInput::make('seniat_retention_percent')
                                    ->label('Retención SENIAT (%)')
                                    ->helperText('Porcentaje de retención aplicable a este proveedor (0 a 100). Use 0 si no aplica retención.')
                                    ->numeric()
                                    ->suffix('%')
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->placeholder('Ej. 75')
                                    ->dehydrateStateUsing(fn (mixed $state): ?float => $state === '' || $state === null
                                        ? null
                                        : (float) $state)
                                    ->prefixIcon(Heroicon::Calculator),
                            ]),
                    ])
                    ->columns(1),
            ])
            ->action(function (array $data, Action $action) use ($onCompleted): void {
                $supplierId = (int) ($action->getArguments()['supplier_id'] ?? 0);
                $supplier = $supplierId > 0
                    ? Supplier::query()->find($supplierId)
                    : null;

                if ($supplier === null) {
                    throw ValidationException::withMessages([
                        'seniat_retention_percent' => 'No se encontró el proveedor seleccionado en la compra.',
                    ]);
                }

                $percent = $data['seniat_retention_percent'] ?? null;
                if ($percent === null || $percent === '') {
                    throw ValidationException::withMessages([
                        'seniat_retention_percent' => 'Indique el porcentaje de retención SENIAT.',
                    ]);
                }

                $actor = auth()->user()?->email
                    ?? auth()->user()?->name
                    ?? 'sistema';

                $supplier->update([
                    'seniat_retention_percent' => (float) $percent,
                    'updated_by' => $actor,
                ]);

                Notification::make()
                    ->title('Retención SENIAT guardada')
                    ->body('Se actualizó el proveedor. Continúe con el resumen de la compra.')
                    ->success()
                    ->send();

                $onCompleted();
            });
    }
}
