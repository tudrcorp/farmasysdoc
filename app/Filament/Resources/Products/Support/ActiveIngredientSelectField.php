<?php

namespace App\Filament\Resources\Products\Support;

use App\Support\Products\ActiveIngredientCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

final class ActiveIngredientSelectField
{
    public static function make(): Select
    {
        return Select::make('active_ingredient')
            ->label('Principio(s) activo(s)')
            ->placeholder('Seleccione uno o más principios activos')
            ->options(fn (): array => ActiveIngredientCatalog::options())
            ->multiple()
            ->searchable()
            ->preload()
            ->native(false)
            ->helperText('Use + para crear un principio nuevo o el lápiz para corregir uno del catálogo.')
            ->createOptionForm([
                TextInput::make('name')
                    ->label('Nombre del principio activo')
                    ->placeholder('Ej. Paracetamol, Amoxicilina/Clavulánico')
                    ->required()
                    ->maxLength(255),
            ])
            ->createOptionModalHeading('Nuevo principio activo')
            ->createOptionAction(
                fn (Action $action): Action => $action
                    ->modalSubmitActionLabel('Crear y agregar')
                    ->modalDescription('Quedará disponible en el catálogo para todos los productos.'),
            )
            ->createOptionUsing(function (array $data): string {
                try {
                    return ActiveIngredientCatalog::createUnique((string) ($data['name'] ?? ''));
                } catch (ValidationException $e) {
                    $message = collect($e->errors())->flatten()->first();
                    Notification::make()
                        ->title('No se pudo crear el principio activo')
                        ->body(is_string($message) ? $message : 'Revise el nombre e intente de nuevo.')
                        ->danger()
                        ->send();

                    throw $e;
                }
            })
            ->suffixAction(
                Action::make('editActiveIngredientInCatalog')
                    ->label('Editar principio')
                    ->icon(Heroicon::PencilSquare)
                    ->iconButton()
                    ->color('gray')
                    ->tooltip('Editar nombre en el catálogo')
                    ->modalHeading('Editar principio activo')
                    ->modalDescription('Actualiza el catálogo y los productos que ya usan ese nombre.')
                    ->modalSubmitActionLabel('Guardar cambios')
                    ->schema([
                        Select::make('ingredient_name')
                            ->label('Principio a editar')
                            ->options(fn (): array => ActiveIngredientCatalog::options())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                if (filled($state)) {
                                    $set('name', (string) $state);
                                }
                            }),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, Select $component): void {
                        try {
                            $newName = ActiveIngredientCatalog::rename(
                                (string) ($data['ingredient_name'] ?? ''),
                                (string) ($data['name'] ?? ''),
                            );
                        } catch (ValidationException $e) {
                            $message = collect($e->errors())->flatten()->first();
                            Notification::make()
                                ->title('No se pudo actualizar')
                                ->body(is_string($message) ? $message : 'Revise los datos.')
                                ->danger()
                                ->send();

                            throw $e;
                        }

                        $state = $component->getState();
                        if (is_array($state)) {
                            $oldName = (string) ($data['ingredient_name'] ?? '');
                            $component->state(array_values(array_unique(array_map(
                                static fn (mixed $item): string => is_string($item) && mb_strtolower(trim($item)) === mb_strtolower(trim($oldName))
                                    ? $newName
                                    : (string) $item,
                                $state,
                            ))));
                        }

                        $component->callAfterStateUpdated();

                        Notification::make()
                            ->title('Principio actualizado')
                            ->body('Se actualizó el catálogo y la selección de este producto.')
                            ->success()
                            ->send();
                    }),
                isInline: false,
            )
            ->columnSpanFull();
    }
}
