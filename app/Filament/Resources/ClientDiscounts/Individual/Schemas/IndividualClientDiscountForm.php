<?php

namespace App\Filament\Resources\ClientDiscounts\Individual\Schemas;

use App\Models\Client;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class IndividualClientDiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Descuento individual')
                    ->description('Asocia un porcentaje a un cliente. Si el cliente estaba en un grupo, se desvincula automáticamente.')
                    ->icon(Heroicon::ReceiptPercent)
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(fn (): array => self::clientOptions())
                            ->getSearchResultsUsing(fn (string $search): array => self::clientOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => self::clientOptionLabel($value))
                            ->visibleOn('create')
                            ->helperText('Clientes que ya tienen descuento individual aparecen en el listado para editar.'),
                        TextInput::make('client_name')
                            ->label('Cliente')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit')
                            ->prefixIcon(Heroicon::User),
                        TextInput::make('customer_discount')
                            ->label('Descuento')
                            ->helperText('Porcentaje entre 0.01 y 100 aplicado sobre el subtotal de toda la venta.')
                            ->numeric()
                            ->inputMode('decimal')
                            ->suffix('%')
                            ->minValue(0.01)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            ->default(5)
                            ->prefixIcon(Heroicon::ReceiptPercent),
                        Placeholder::make('group_warning')
                            ->hiddenLabel()
                            ->content('Este cliente pertenece a un grupo. Al guardar se quitará del grupo y quedará solo con descuento individual.')
                            ->visible(fn (Get $get): bool => filled($get('client_id')) && self::clientBelongsToGroup((int) $get('client_id')))
                            ->visibleOn('create'),
                    ])
                    ->columns(1),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function clientOptions(?string $search = null): array
    {
        $query = Client::query()
            ->where(function ($builder): void {
                $builder->where('customer_discount', '<=', 0)
                    ->orWhereNull('customer_discount');
            })
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

    private static function clientOptionLabel(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $client = Client::query()->find((int) $value);

        return $client instanceof Client ? self::clientLabel($client) : null;
    }

    private static function clientLabel(Client $client): string
    {
        $doc = filled($client->document_number) ? ' · '.$client->document_number : '';
        $group = $client->discountGroups()->first();
        $suffix = $group ? ' (en grupo «'.$group->name.'»)' : '';

        return $client->name.$doc.$suffix;
    }

    private static function clientBelongsToGroup(int $clientId): bool
    {
        return Client::query()
            ->whereKey($clientId)
            ->whereHas('discountGroups')
            ->exists();
    }
}
