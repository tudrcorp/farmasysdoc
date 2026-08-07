<?php

namespace App\Filament\Resources\ClientDiscounts\Groups\Schemas;

use App\Models\Client;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ClientDiscountGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Grupo de descuento')
                    ->description('El porcentaje se aplica a toda la venta de los clientes asociados. Un cliente no puede tener descuento individual y de grupo a la vez.')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del grupo')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Tag),
                        TextInput::make('discount_percent')
                            ->label('Descuento')
                            ->helperText('Porcentaje entre 0 y 100 aplicado sobre el subtotal de la venta.')
                            ->numeric()
                            ->inputMode('decimal')
                            ->suffix('%')
                            ->minValue(0.01)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            ->prefixIcon(Heroicon::ReceiptPercent),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->helperText('Si está inactivo, los clientes del grupo no reciben descuento en caja.')
                            ->default(true),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                        Select::make('client_ids')
                            ->label('Clientes del grupo')
                            ->helperText('Al asociarlos se elimina cualquier descuento individual. Un cliente solo puede pertenecer a un grupo.')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => self::clientOptions())
                            ->getSearchResultsUsing(fn (string $search): array => self::clientOptions($search))
                            ->getOptionLabelsUsing(fn (array $values): array => Client::query()
                                ->whereIn('id', $values)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Client $client): array => [
                                    $client->id => self::clientLabel($client),
                                ])
                                ->all())
                            ->dehydrated()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function clientOptions(?string $search = null): array
    {
        $query = Client::query()
            ->orderBy('name')
            ->limit(50);

        if (filled($search)) {
            $term = '%'.$search.'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('name', 'like', $term)
                    ->orWhere('document_number', 'like', $term);
            });
        }

        return $query->get()
            ->mapWithKeys(fn (Client $client): array => [
                $client->id => self::clientLabel($client),
            ])
            ->all();
    }

    private static function clientLabel(Client $client): string
    {
        $doc = filled($client->document_number) ? ' · '.$client->document_number : '';

        return $client->name.$doc;
    }
}
