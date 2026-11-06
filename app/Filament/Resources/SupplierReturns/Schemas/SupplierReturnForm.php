<?php

namespace App\Filament\Resources\SupplierReturns\Schemas;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierReturnAttachment;
use App\Services\Purchases\SupplierReturnNotifier;
use App\Support\Filament\BranchAuthScope;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SupplierReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recepción')
                    ->description('Mercancía que llegó de más. Al guardar se avisa a Administración para revisarla.')
                    ->icon(Heroicon::Truck)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2, 'lg' => 4])
                            ->schema([
                                Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->options(fn (): array => self::branchOptions())
                                    ->default(fn (): ?int => count(self::branchOptions()) === 1 ? (int) array_key_first(self::branchOptions()) : null)
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('purchase_id', null)),
                                Select::make('supplier_id')
                                    ->label('Proveedor que entregó')
                                    ->options(fn (): array => Supplier::query()
                                        ->orderBy('legal_name')
                                        ->get(['id', 'legal_name', 'trade_name'])
                                        ->mapWithKeys(fn (Supplier $supplier): array => [$supplier->id => $supplier->trade_name ?: $supplier->legal_name])
                                        ->all())
                                    ->searchable()
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('purchase_id', null)),
                                Select::make('purchase_id')
                                    ->label('Compra relacionada (opcional)')
                                    ->helperText('Si la elige, la cantidad facturada se trae de la compra al escanear.')
                                    ->options(fn (Get $get): array => self::purchaseOptions($get))
                                    ->disabled(fn (Get $get): bool => blank($get('supplier_id')) || blank($get('branch_id')))
                                    ->searchable()
                                    ->native(false)
                                    ->live(),
                                DatePicker::make('received_on')
                                    ->label('Fecha de recepción')
                                    ->default(now())
                                    ->maxDate(now())
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),
                                Textarea::make('notes')
                                    ->label('Nota')
                                    ->placeholder('Qué llegó de más, número de factura o nota de entrega, estado de la mercancía…')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Productos recibidos de más')
                    ->description('Escanee o escriba el código de barras: si el producto existe se completa el nombre. La diferencia se calcula sola.')
                    ->icon(Heroicon::QrCode)
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('purchase_item_id'),
                                Grid::make(['default' => 2, 'md' => 6])
                                    ->schema([
                                        TextInput::make('barcode')
                                            ->label('Código de barras')
                                            ->maxLength(64)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => self::fillFromBarcode($state, $get, $set))
                                            ->prefixIcon(Heroicon::QrCode)
                                            ->columnSpan(['default' => 2, 'md' => 2]),
                                        Select::make('product_id')
                                            ->label('Varios productos tienen ese código: elija uno')
                                            ->options(fn (Get $get): array => Product::matchingBarcode((string) $get('barcode'))->pluck('name', 'id')->all())
                                            ->visible(fn (Get $get): bool => Product::matchingBarcode((string) $get('barcode'))->count() > 1)
                                            ->native(false)
                                            ->live()
                                            ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => self::selectProduct(filled($state) ? (int) $state : null, $get, $set))
                                            ->columnSpan(['default' => 2, 'md' => 6]),
                                        TextInput::make('product_name')
                                            ->label('Producto')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText(fn (Get $get): ?string => filled($get('barcode')) && Product::matchingBarcode((string) $get('barcode'))->isEmpty()
                                                ? 'No está en el catálogo: escriba el nombre.'
                                                : null)
                                            ->columnSpan(['default' => 2, 'md' => 2]),
                                        TextInput::make('quantity_invoiced')
                                            ->label('Facturada')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required()
                                            ->live(onBlur: true),
                                        TextInput::make('quantity_received')
                                            ->label('Recibida')
                                            ->numeric()
                                            ->minValue(0.001)
                                            ->required()
                                            ->gt('quantity_invoiced')
                                            ->validationMessages(['gt' => 'Debe ser mayor que la facturada.'])
                                            ->live(onBlur: true),
                                        TextEntry::make('excess')
                                            ->label('De más')
                                            ->state(function (Get $get): string {
                                                $excess = (float) $get('quantity_received') - (float) $get('quantity_invoiced');

                                                return $excess > 0 ? SupplierReturnNotifier::quantity($excess) : '—';
                                            })
                                            ->badge()
                                            ->color(fn (Get $get): string => ((float) $get('quantity_received') - (float) $get('quantity_invoiced')) > 0 ? 'warning' : 'gray')
                                            ->columnSpan(['default' => 2, 'md' => 6]),
                                    ]),
                            ])
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar producto')
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (?array $state): ?string => filled($state['product_name'] ?? null) ? (string) $state['product_name'] : 'Producto')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Fotos y soportes')
                    ->description('Opcional: fotos de la mercancía, nota de entrega o factura. PDF o imagen.')
                    ->icon(Heroicon::Camera)
                    ->collapsible()
                    ->schema([
                        FileUpload::make('attachments')
                            ->hiddenLabel()
                            ->multiple()
                            ->disk(fn (): string => SupplierReturnAttachment::disk())
                            ->directory(fn (): string => (string) config('supplier_returns.attachment_directory'))
                            ->visibility('private')
                            ->acceptedFileTypes(fn (): array => (array) config('supplier_returns.attachment_accepted_mime_types'))
                            ->maxSize(fn (): int => (int) config('supplier_returns.attachment_max_size_kb'))
                            ->maxFiles(fn (): int => (int) config('supplier_returns.attachment_max_files'))
                            ->storeFileNamesIn('attachment_names')
                            ->openable()
                            ->downloadable()
                            ->panelLayout('grid'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    public static function branchOptions(): array
    {
        return BranchAuthScope::branchOptions();
    }

    /**
     * @return array<int, string>
     */
    private static function purchaseOptions(Get $get): array
    {
        if (blank($get('supplier_id')) || blank($get('branch_id'))) {
            return [];
        }

        return Purchase::query()
            ->where('supplier_id', (int) $get('supplier_id'))
            ->where('branch_id', (int) $get('branch_id'))
            ->latest('id')
            ->limit(50)
            ->get(['id', 'purchase_number', 'created_at'])
            ->mapWithKeys(fn (Purchase $purchase): array => [
                $purchase->id => trim(($purchase->purchase_number ?? '#'.$purchase->id).' · '.$purchase->created_at?->format('d/m/Y')),
            ])
            ->all();
    }

    private static function fillFromBarcode(?string $barcode, Get $get, Set $set): void
    {
        $matches = Product::matchingBarcode((string) $barcode);

        if ($matches->count() !== 1) {
            $set('product_id', null);
            $set('purchase_item_id', null);

            if ($matches->count() > 1) {
                $set('product_name', null);
            }

            return;
        }

        self::selectProduct((int) $matches->first()->id, $get, $set);
    }

    private static function selectProduct(?int $productId, Get $get, Set $set): void
    {
        $product = $productId !== null ? Product::query()->find($productId, ['id', 'name']) : null;

        $set('product_id', $product?->id);
        $set('purchase_item_id', null);

        if (! $product instanceof Product) {
            return;
        }

        $set('product_name', $product->name);

        $purchaseId = $get('../../purchase_id');

        if (blank($purchaseId)) {
            return;
        }

        $purchaseItem = PurchaseItem::query()
            ->where('purchase_id', (int) $purchaseId)
            ->where('product_id', $product->id)
            ->first(['id', 'quantity_ordered']);

        $set('purchase_item_id', $purchaseItem?->id);

        if ($purchaseItem instanceof PurchaseItem) {
            $set('quantity_invoiced', (float) $purchaseItem->quantity_ordered);
        }
    }
}
