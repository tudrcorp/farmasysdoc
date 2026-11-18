<?php

namespace App\Filament\Resources\Formularies\Actions;

use App\Filament\Resources\Formularies\FormularyResource;
use App\Models\Formulary;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Copia un catálogo con sus categorías para armar una variante sin empezar de cero.
 */
class DuplicateFormularyAction
{
    public static function make(): Action
    {
        return Action::make('duplicateFormulary')
            ->label('Duplicar')
            ->icon(Heroicon::DocumentDuplicate)
            ->color('gray')
            ->visible(fn (): bool => FormularyResource::canCreate())
            ->modalHeading('Duplicar catálogo')
            ->modalDescription('Se crea un catálogo nuevo con las mismas categorías, sin planes asociados.')
            ->fillForm(fn (Formulary $record): array => [
                'code' => Formulary::nextCode(),
                'name' => $record->name.' (copia)',
            ])
            ->schema([
                TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->maxLength(30)
                    ->unique(Formulary::class, 'code')
                    ->validationMessages(['unique' => 'Ya existe un catálogo con ese código.']),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Formulary $record, array $data, Action $action): void {
                $copy = DB::transaction(function () use ($record, $data): Formulary {
                    $copy = Formulary::query()->create([
                        'code' => $data['code'],
                        'name' => $data['name'],
                        'description' => $record->description,
                        'is_active' => true,
                        'requires_prescription' => $record->requires_prescription,
                        'requires_authorization' => $record->requires_authorization,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                    $copy->categories()->sync($record->categories()->pluck('formulary_categories.id')->all());

                    return $copy;
                });

                Notification::make()->title('Catálogo duplicado')->success()->send();
                $action->redirect(FormularyResource::getUrl('edit', ['record' => $copy]));
            });
    }
}
