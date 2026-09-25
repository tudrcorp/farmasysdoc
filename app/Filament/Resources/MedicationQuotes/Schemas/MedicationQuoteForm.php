<?php

namespace App\Filament\Resources\MedicationQuotes\Schemas;

use App\Enums\MedicationQuoteAdjustment;
use App\Models\Inventory;
use App\Models\Product;
use App\Support\Purchases\MedicationQuoteTotals;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component as LivewireComponent;

class MedicationQuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Datos de la cotización')
                    ->description('La cotización queda a nombre del solicitante. El ajuste se aplica al total y no se imprime.')
                    ->icon(Heroicon::User)
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('requester_name')
                            ->label('Nombre o razón social')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Identification)
                            ->columnSpanFull(),
                        TextInput::make('requester_document')
                            ->label('Cédula o RIF')
                            ->required()
                            ->maxLength(20)
                            ->prefixIcon(Heroicon::Identification)
                            ->rule(function (): \Closure {
                                return function (string $attribute, mixed $value, \Closure $fail): void {
                                    $compact = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
                                    $isDocument = preg_match('/^[VEJGPC]?\d{6,10}$/', $compact) === 1;
                                    if (! $isDocument) {
                                        $fail('Indique una cédula o un RIF válido.');
                                    }
                                };
                            }),
                        TextInput::make('requester_phone')
                            ->label('Teléfono')
                            ->required()
                            ->tel()
                            ->maxLength(30)
                            ->prefixIcon(Heroicon::Phone),
                        TextInput::make('requester_email')
                            ->label('Correo electrónico')
                            ->required()
                            ->email()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Envelope)
                            ->columnSpanFull(),
                        Select::make('adjustment_kind')
                            ->label('Tipo de ajuste')
                            ->options(MedicationQuoteAdjustment::options())
                            ->default(MedicationQuoteAdjustment::None->value)
                            ->required()
                            ->native(false)
                            ->live(),
                        TextInput::make('adjustment_percent')
                            ->label('Porcentaje')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->suffix('%')
                            ->required()
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => $get('adjustment_kind') !== MedicationQuoteAdjustment::None->value),
                        Placeholder::make('quote_total_preview')
                            ->label('Total a cobrar')
                            ->content(function (Get $get): string {
                                $totals = MedicationQuoteTotals::calculate(
                                    is_array($get('lines')) ? $get('lines') : [],
                                    $get('adjustment_kind'),
                                    (float) ($get('adjustment_percent') ?? 0),
                                );

                                return 'USD '.number_format($totals['total_usd'], 2, ',', '.');
                            }),
                        Textarea::make('notes')
                            ->label('Observaciones para el solicitante')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Medicamentos')
                    ->description('Busque en el inventario. Si no existe, créelo. También puede cotizar productos sin existencia.')
                    ->icon(Heroicon::Beaker)
                    ->columnSpanFull()
                    ->headerActions([
                        Action::make('openNewQuoteProduct')
                            ->label('Crear producto')
                            ->icon(Heroicon::Plus)
                            ->action(function (LivewireComponent $livewire): void {
                                $livewire->mountAction('registerQuoteProduct');
                            }),
                    ])
                    ->schema([
                        Repeater::make('lines')
                            ->relationship()
                            ->hiddenLabel()
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->addActionLabel('Agregar medicamento')
                            ->columnSpanFull()
                            ->live()
                            ->table([
                                TableColumn::make('Medicamento')->width('46%'),
                                TableColumn::make('Cantidad')->width('14%'),
                                TableColumn::make('Precio USD')->width('18%'),
                                TableColumn::make('Importe')->width('18%'),
                            ])
                            ->schema([
                                Select::make('product_id')
                                    ->label('Medicamento')
                                    ->relationship(
                                        name: 'product',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('name'),
                                    )
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => self::productOptions($search))
                                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::productLabel($value))
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                        $product = filled($state) ? Product::query()->find($state) : null;
                                        if (! $product instanceof Product) {
                                            return;
                                        }

                                        $set('description', $product->name);
                                        $set('unit_price_usd', number_format((float) $product->sale_price, 2, '.', ''));
                                        self::syncLineTotal($get, $set);
                                    }),
                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.001)
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncLineTotal($get, $set)),
                                TextInput::make('unit_price_usd')
                                    ->label('Precio USD')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->prefix('USD')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncLineTotal($get, $set)),
                                TextInput::make('line_total_usd')
                                    ->label('Importe')
                                    ->numeric()
                                    ->prefix('USD')
                                    ->readOnly()
                                    ->dehydrated(),
                                TextInput::make('description')
                                    ->hidden()
                                    ->dehydrated()
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<int|string, string>
     */
    public static function productOptions(string $search): array
    {
        $term = trim($search);
        if ($term === '') {
            return [];
        }

        return Product::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('barcode', 'like', '%'.$term.'%')
                    ->orWhere('sku', 'like', '%'.$term.'%')
                    ->orWhere('brand', 'like', '%'.$term.'%');
            })
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->getKey() => self::formatProduct($product),
            ])
            ->all();
    }

    public static function productLabel(mixed $value): ?string
    {
        $product = filled($value) ? Product::query()->find($value) : null;

        return $product instanceof Product ? self::formatProduct($product) : null;
    }

    public static function formatProduct(Product $product): string
    {
        $stock = self::stockFor((int) $product->getKey());
        $stockLabel = $stock > 0 ? self::formatQty($stock).' en existencia' : 'sin existencia';

        return $product->name.' · USD '.number_format((float) $product->sale_price, 2, ',', '.').' · '.$stockLabel;
    }

    public static function stockFor(int $productId): float
    {
        return (float) Inventory::query()->where('product_id', $productId)->sum('quantity');
    }

    public static function formatQty(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, ',', '.'), '0'), ',');
    }

    public static function syncLineTotal(Get $get, Set $set): void
    {
        $quantity = max(0, (float) ($get('quantity') ?? 0));
        $unitPrice = max(0, (float) ($get('unit_price_usd') ?? 0));
        $set('line_total_usd', number_format(round($quantity * $unitPrice, 2), 2, '.', ''));
    }
}
