<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Support\Finance\DefaultVatRate;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación comercial')
                    ->description('Códigos, nombre y clasificación del artículo en catálogo y punto de venta.')
                    ->icon(Heroicon::Cube)
                    ->schema([
                        TextEntry::make('supplier_display')
                            ->label('Proveedor principal')
                            ->placeholder('—')
                            ->icon(Heroicon::Truck)
                            ->getStateUsing(function (Product $record): string {
                                $supplier = $record->supplier;

                                if ($supplier === null) {
                                    return '—';
                                }

                                return $supplier->trade_name ?: $supplier->legal_name;
                            }),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('barcode')
                                    ->label('Código de barras / EAN')
                                    ->placeholder('—')
                                    ->icon(Heroicon::QrCode)
                                    ->copyable(),
                                TextEntry::make('slug')
                                    ->label('Slug (URL)')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Link)
                                    ->copyable(),
                            ]),
                        TextEntry::make('name')
                            ->label('Nombre comercial')
                            ->icon(Heroicon::ShoppingBag)
                            ->columnSpanFull(),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->prose(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('brand')
                                    ->label('Marca / laboratorio')
                                    ->placeholder('—')
                                    ->icon(Heroicon::BuildingStorefront),
                                TextEntry::make('presentation')
                                    ->label('Presentación comercial')
                                    ->placeholder('—')
                                    ->icon(Heroicon::CubeTransparent),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Imágenes del producto')
                    ->description('Vista previa con estilo vidrio iOS.')
                    ->icon(Heroicon::Photo)
                    ->schema([
                        ImageEntry::make('image')
                            ->label('Fotografía principal')
                            ->disk('public')
                            ->visibility('public')
                            ->height(220)
                            ->placeholder('Sin imagen registrada')
                            ->extraImgAttributes([
                                'class' => 'fi-farmaadmin-ios-product-infolist-img',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'fi-farmaadmin-ios-product-images-infolist-section',
                    ]),

                Section::make('Unidad de venta y precios')
                    ->description('Precio de venta (lista) = costo + (costo × % de ganancia de la categoría). Contenido comercial y valores únicos para todas las sucursales.')
                    ->icon(Heroicon::CurrencyDollar)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('productCategory.name')
                                    ->label('Categoría')
                                    ->placeholder('—')
                                    ->badge()
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('warning')
                                    ->icon(Heroicon::Swatch),
                                TextEntry::make('category_profit_percent')
                                    ->label('Margen de ganancia (categoría)')
                                    ->badge()
                                    ->size('lg')
                                    ->weight('bold')
                                    ->state(fn (Product $record): string => number_format(max(0.0, (float) ($record->productCategory?->profit_percentage ?? 0)), 2, '.', ',').' %')
                                    ->color('warning')
                                    ->icon(Heroicon::ChartBarSquare)
                                    ->helperText('Porcentaje configurado en la categoría del producto; define el precio lista sobre el costo.'),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextEntry::make('sale_price')
                                    ->label('Precio lista (calculado)')
                                    ->money('USD')
                                    ->icon(Heroicon::Banknotes),
                                TextEntry::make('effective_sale_unit')
                                    ->label('Precio efectivo (tras desc.)')
                                    ->getStateUsing(fn (Product $record): string => '$'.number_format($record->effectiveSaleUnitPrice(), 2, '.', ','))
                                    ->icon(Heroicon::ShoppingCart),
                                TextEntry::make('cost_price')
                                    ->label('Costo de compra')
                                    ->placeholder('—')
                                    ->money('USD')
                                    ->icon(Heroicon::ReceiptPercent),
                                TextEntry::make('discount_percent')
                                    ->label('Descuento')
                                    ->suffix(' %')
                                    ->numeric(2)
                                    ->icon(Heroicon::Tag),
                            ]),
                        TextEntry::make('vat_status')
                            ->label('Régimen IVA')
                            ->badge()
                            ->size('lg')
                            ->weight('bold')
                            ->state(fn (Product $record): string => $record->applies_vat ? 'GRAVA IVA' : 'NO GRAVA IVA')
                            ->color(fn (Product $record): string => $record->applies_vat ? 'success' : 'danger')
                            ->icon(fn (Product $record): Heroicon => $record->applies_vat ? Heroicon::CheckBadge : Heroicon::XCircle),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('final_list_price_with_vat')
                                    ->label('Precio final con IVA (lista)')
                                    ->money('USD')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->state(function (Product $record): float {
                                        $base = (float) ($record->sale_price ?? 0);
                                        $rate = max(0.0, DefaultVatRate::percent());

                                        return round($base + ($base * $rate / 100), 2);
                                    })
                                    ->visible(fn (Product $record): bool => $record->applies_vat)
                                    ->icon(Heroicon::Banknotes)
                                    ->helperText(fn (): string => 'Precio lista + IVA ('.rtrim(rtrim(number_format(DefaultVatRate::percent(), 2, '.', ''), '0'), '.').' % tasa del sistema).'),
                                TextEntry::make('final_list_price_without_vat')
                                    ->label('Precio final sin IVA')
                                    ->money('USD')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->state(fn (Product $record): float => round((float) ($record->sale_price ?? 0), 2))
                                    ->visible(fn (Product $record): bool => ! $record->applies_vat)
                                    ->icon(Heroicon::Banknotes)
                                    ->helperText('Producto exento: el precio de lista es el precio final (sin componente IVA).'),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('unit_of_measure')
                                    ->label('Unidad de medida de venta')
                                    ->icon(Heroicon::Scale),
                                TextEntry::make('unit_content')
                                    ->label('Contenido por unidad (número)')
                                    ->numeric(3)
                                    ->placeholder('—')
                                    ->icon(Heroicon::Hashtag),
                                TextEntry::make('net_content_label')
                                    ->label('Etiqueta de contenido neto')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Beaker),
                            ]),
                        TextEntry::make('express_pricing_summary')
                            ->label('Estructura de Costos Express')
                            ->icon(Heroicon::BuildingStorefront)
                            ->state(function (Product $record): string {
                                $count = is_array($record->express_branch_prices)
                                    ? count($record->express_branch_prices)
                                    : 0;

                                if ($count === 0) {
                                    return 'No hay estructuras express configuradas.';
                                }

                                return 'Precios express calculados para '.$count.' sucursal(es).';
                            })
                            ->helperText('La tabla siguiente muestra el precio final del producto por sucursal express, según el % de ganancia configurado.')
                            ->badge()
                            ->color(fn (Product $record): string => filled($record->express_branch_prices) ? 'success' : 'gray')
                            ->columnSpanFull(),
                        RepeatableEntry::make('express_branch_prices')
                            ->label('Detalle por sucursal express')
                            ->placeholder('No hay precios express generados para este producto todavía.')
                            ->table([
                                TableColumn::make('Sucursal'),
                                TableColumn::make('% ganancia')
                                    ->width('8rem')
                                    ->alignment(Alignment::Center),
                                TableColumn::make('Final sin IVA')
                                    ->alignment(Alignment::End),
                                TableColumn::make('Final con IVA')
                                    ->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('branch_name')
                                    ->label('')
                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('profit_percentage')
                                    ->label('')
                                    ->alignment(Alignment::Center)
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' %'),
                                TextEntry::make('final_price_without_vat')
                                    ->label('')
                                    ->alignment(Alignment::End)
                                    ->formatStateUsing(fn ($state): string => '$'.number_format((float) $state, 2, ',', '.')),
                                TextEntry::make('final_price_with_vat')
                                    ->label('')
                                    ->alignment(Alignment::End)
                                    ->weight('medium')
                                    ->formatStateUsing(fn ($state): string => '$'.number_format((float) $state, 2, ',', '.')),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Información farmacéutica y registro')
                    ->description('Datos para medicamentos: principio activo, registro sanitario y requisitos legales.')
                    ->icon(Heroicon::Beaker)
                    ->schema([
                        TextEntry::make('active_ingredient')
                            ->label('Principio(s) activo(s)')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->prose(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('concentration')
                                    ->label('Concentración')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Beaker),
                                TextEntry::make('presentation_type')
                                    ->label('Forma farmacéutica')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Cube),
                            ]),
                        TextEntry::make('health_registration_number')
                            ->label('Registro sanitario (INVIMA u otro)')
                            ->placeholder('—')
                            ->icon(Heroicon::DocumentCheck)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                IconEntry::make('requires_prescription')
                                    ->label('Requiere fórmula médica')
                                    ->boolean(),
                                IconEntry::make('is_controlled_substance')
                                    ->label('Sustancia controlada / psicotrópico')
                                    ->boolean(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Composición, alérgenos y nutrición')
                    ->description('Relevante para alimentos, suplementos y algunos productos de cuidado personal.')
                    ->icon(Heroicon::Cake)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('ingredients')
                            ->label('Ingredientes')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->prose(),
                        TextEntry::make('allergens')
                            ->label('Alérgenos declarados')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->prose(),
                        TextEntry::make('nutritional_information')
                            ->label('Información nutricional')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->prose(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Fabricación y dispositivo médico')
                    ->description('Datos de fabricante, modelo, garantía y clase reglamentaria.')
                    ->icon(Heroicon::WrenchScrewdriver)
                    ->collapsed()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('manufacturer')
                                    ->label('Fabricante')
                                    ->placeholder('—')
                                    ->icon(Heroicon::BuildingOffice2),
                                TextEntry::make('model')
                                    ->label('Modelo / referencia')
                                    ->placeholder('—')
                                    ->icon(Heroicon::CpuChip),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('warranty_months')
                                    ->label('Garantía (meses)')
                                    ->placeholder('—')
                                    ->numeric(0)
                                    ->icon(Heroicon::CalendarDays),
                                TextEntry::make('medical_device_class')
                                    ->label('Clase de dispositivo médico')
                                    ->placeholder('—')
                                    ->icon(Heroicon::ShieldCheck),
                            ]),
                        IconEntry::make('requires_calibration')
                            ->label('Requiere calibración periódica')
                            ->boolean(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Almacenamiento y visibilidad')
                    ->description('Condiciones de conservación y disponibilidad en catálogo.')
                    ->icon(Heroicon::ArchiveBox)
                    ->schema([
                        TextEntry::make('storage_conditions')
                            ->label('Condiciones de almacenamiento')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->prose(),
                        TextEntry::make('expiration_date')
                            ->label('Fecha de vencimiento')
                            ->placeholder('—')
                            ->date('d/m/Y')
                            ->icon(Heroicon::CalendarDays),
                        IconEntry::make('is_active')
                            ->label('Producto activo')
                            ->boolean(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Trazabilidad del registro en el sistema.')
                    ->icon(Heroicon::Clock)
                    ->collapsed()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::User),
                                TextEntry::make('updated_by')
                                    ->label('Última modificación por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::UserCircle),
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
