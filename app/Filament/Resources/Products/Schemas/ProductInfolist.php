<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
                        TextEntry::make('productCategory.name')
                            ->label('Categoría')
                            ->placeholder('—')
                            ->icon(Heroicon::Swatch),
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
