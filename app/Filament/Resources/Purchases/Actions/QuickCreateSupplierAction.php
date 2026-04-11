<?php

namespace App\Filament\Resources\Purchases\Actions;

use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

final class QuickCreateSupplierAction
{
    /**
     * Tras crear el proveedor, se invoca con su id para seleccionarlo en el formulario de compra.
     *
     * @param  callable(int $supplierId): void  $onCreated
     */
    public static function make(callable $onCreated): Action
    {
        return Action::make('quickCreateSupplier')
            ->label('Nuevo proveedor')
            ->icon(Heroicon::UserPlus)
            ->color('info')
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--info farmadoc-ios-action--liquid-glass',
            ])
            ->modalWidth(Width::Large)
            ->modalHeading('Registrar proveedor')
            ->modalDescription('Datos mínimos para poder asociar la compra. Podrá completar el ficha del proveedor después en el catálogo.')
            ->modalSubmitActionLabel('Crear y seleccionar')
            ->modalIcon(Heroicon::Truck)
            ->schema([
                Grid::make(1)
                    ->schema([
                        TextInput::make('legal_name')
                            ->label('Razón social')
                            ->placeholder('Según RIF o documento')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::BuildingOffice2),
                        TextInput::make('trade_name')
                            ->label('Nombre comercial')
                            ->placeholder('Opcional — marca o nombre en factura')
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::BuildingStorefront),
                        TextInput::make('tax_id')
                            ->label('RIF / NIT / identificación fiscal')
                            ->placeholder('Ej. J123456789 o NIT sin símbolos extra')
                            ->helperText('Se normaliza al guardar (mayúsculas, sin guiones ni espacios). Debe ser único.')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Identification),
                        TextInput::make('mobile_phone')
                            ->label('Teléfono / celular')
                            ->tel()
                            ->placeholder('Opcional')
                            ->helperText('Puede incluir espacios o guiones; solo se guardan dígitos.')
                            ->maxLength(40)
                            ->prefixIcon(Heroicon::DevicePhoneMobile),
                    ]),
            ])
            ->action(function (array $data) use ($onCreated): void {
                $taxId = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['tax_id'] ?? '')));
                if ($taxId === '') {
                    throw ValidationException::withMessages([
                        'tax_id' => 'Ingrese un RIF / NIT / identificación fiscal válida.',
                    ]);
                }

                if (Supplier::query()->where('tax_id', $taxId)->exists()) {
                    throw ValidationException::withMessages([
                        'tax_id' => 'Ya existe un proveedor con ese RIF / NIT.',
                    ]);
                }

                $actor = auth()->user()?->email
                    ?? auth()->user()?->name
                    ?? 'sistema';

                $mobile = $data['mobile_phone'] ?? null;
                $mobile = filled($mobile) ? preg_replace('/[^0-9]/', '', (string) $mobile) : null;
                $mobile = $mobile === '' ? null : $mobile;

                $supplier = Supplier::query()->create([
                    'code' => null,
                    'legal_name' => trim((string) $data['legal_name']),
                    'trade_name' => filled($data['trade_name'] ?? null) ? trim((string) $data['trade_name']) : null,
                    'tax_id' => $taxId,
                    'email' => null,
                    'phone' => null,
                    'mobile_phone' => $mobile,
                    'website' => null,
                    'address' => null,
                    'city' => null,
                    'state' => null,
                    'country' => 'Colombia',
                    'contact_name' => null,
                    'contact_email' => null,
                    'contact_phone' => null,
                    'payment_terms' => null,
                    'notes' => null,
                    'is_active' => true,
                    'created_by' => $actor,
                    'updated_by' => $actor,
                ]);

                $supplier->forceFill([
                    'code' => Supplier::formatCode($supplier->getKey()),
                ])->save();

                $onCreated((int) $supplier->getKey());

                Notification::make()
                    ->title('Proveedor creado')
                    ->body('Se seleccionó automáticamente en esta compra.')
                    ->success()
                    ->send();
            });
    }
}
