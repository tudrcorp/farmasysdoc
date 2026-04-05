<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\ConvenioType;
use App\Enums\OrderFulfillmentType;
use App\Enums\OrderPartnerCashPaymentMethod;
use App\Enums\OrderPartnerPaymentTerms;
use App\Enums\OrderStatus;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

class OrderInfolist
{
    /**
     * @param  bool  $enableAdminResourceLinks  Si es false (p. ej. panel aliados), no enlaza a recursos del panel Farmaadmin.
     */
    public static function configure(Schema $schema, bool $enableAdminResourceLinks = true): Schema
    {
        return $schema
            ->components([
                Section::make('Pedido')
                    ->description('Identificación, estado y participantes.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('Número de orden')
                                    ->icon(Heroicon::Hashtag)
                                    ->weight('medium')
                                    ->copyable()
                                    ->copyMessage('Número copiado'),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->formatStateUsing(fn (?OrderStatus $state): string => $state instanceof OrderStatus ? $state->label() : '—')
                                    ->color(fn (?OrderStatus $state): string => $state instanceof OrderStatus ? $state->filamentColor() : 'gray'),
                                TextEntry::make('client.name')
                                    ->label('Cliente')
                                    ->icon(Heroicon::User)
                                    ->placeholder('—')
                                    ->url(fn (Order $record): ?string => $enableAdminResourceLinks && $record->client_id
                                        ? ClientResource::getUrl('view', ['record' => $record->client_id], isAbsolute: false)
                                        : null)
                                    ->openUrlInNewTab(false),
                                TextEntry::make('branch.name')
                                    ->label('Sucursal')
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->placeholder('—')
                                    ->url(fn (Order $record): ?string => $enableAdminResourceLinks && $record->branch_id
                                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                                        : null)
                                    ->openUrlInNewTab(false),
                                TextEntry::make('partner_summary')
                                    ->label('Compañía aliada')
                                    ->state(function (Order $record): string {
                                        if (! $record->partner_company_id) {
                                            return 'Pedido interno / sin aliado';
                                        }
                                        $p = $record->partnerCompany;
                                        if ($p === null) {
                                            return filled($record->partner_company_code)
                                                ? (string) $record->partner_company_code
                                                : '—';
                                        }
                                        $n = filled($p->trade_name) ? $p->trade_name : $p->legal_name;

                                        return filled($p->code) ? $n.' ('.$p->code.')' : $n;
                                    })
                                    ->icon(Heroicon::BuildingOffice2)
                                    ->url(function (Order $record): ?string {
                                        if ($record->partner_company_id === null) {
                                            return null;
                                        }
                                        $user = auth()->user();
                                        if (! $user instanceof User || ! $user->isAdministrator()) {
                                            return null;
                                        }

                                        return PartnerCompanyResource::getUrl('view', ['record' => $record->partner_company_id], isAbsolute: false);
                                    })
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                TextEntry::make('partner_fulfillment_type')
                                    ->label('Tipo de entrega')
                                    ->icon(Heroicon::Truck)
                                    ->placeholder('—')
                                    ->formatStateUsing(fn (?OrderFulfillmentType $state): string => $state instanceof OrderFulfillmentType ? $state->label() : '—')
                                    ->visible(fn (Order $record): bool => filled($record->partner_company_id)),
                                TextEntry::make('partner_payment_terms')
                                    ->label('Forma de pago')
                                    ->icon(Heroicon::Banknotes)
                                    ->placeholder('—')
                                    ->formatStateUsing(fn (?OrderPartnerPaymentTerms $state): string => $state instanceof OrderPartnerPaymentTerms ? $state->label() : '—')
                                    ->visible(fn (Order $record): bool => filled($record->partner_company_id)),
                                TextEntry::make('partner_cash_payment_method')
                                    ->label('Medio de pago (de contado)')
                                    ->icon(Heroicon::DevicePhoneMobile)
                                    ->placeholder('—')
                                    ->formatStateUsing(fn (?OrderPartnerCashPaymentMethod $state): string => $state instanceof OrderPartnerCashPaymentMethod ? $state->label() : '—')
                                    ->visible(fn (Order $record): bool => filled($record->partner_company_id)
                                        && $record->partner_payment_terms === OrderPartnerPaymentTerms::Cash),
                                TextEntry::make('partner_pago_movil_reference')
                                    ->label('Referencia pago móvil')
                                    ->icon(Heroicon::Hashtag)
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Referencia copiada')
                                    ->visible(fn (Order $record): bool => filled($record->partner_company_id)
                                        && $record->partner_payment_terms === OrderPartnerPaymentTerms::Cash
                                        && $record->partner_cash_payment_method === OrderPartnerCashPaymentMethod::PagoMovil),
                                TextEntry::make('partner_zelle_reference_email')
                                    ->label('Correo referencia Zelle')
                                    ->icon(Heroicon::Envelope)
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Correo copiado')
                                    ->visible(fn (Order $record): bool => filled($record->partner_company_id)
                                        && $record->partner_payment_terms === OrderPartnerPaymentTerms::Cash
                                        && $record->partner_cash_payment_method === OrderPartnerCashPaymentMethod::Zelle),
                                TextEntry::make('partner_zelle_transaction_number')
                                    ->label('Nº transacción Zelle')
                                    ->icon(Heroicon::Identification)
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Referencia copiada')
                                    ->visible(fn (Order $record): bool => filled($record->partner_company_id)
                                        && $record->partner_payment_terms === OrderPartnerPaymentTerms::Cash
                                        && $record->partner_cash_payment_method === OrderPartnerCashPaymentMethod::Zelle),
                                TextEntry::make('is_wholesale')
                                    ->label('Tipo de pedido')
                                    ->badge()
                                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Al mayor (cajas)' : 'Al detalle (unidades)')
                                    ->color(fn (?bool $state): string => $state ? 'warning' : 'gray')
                                    ->icon(Heroicon::Scale),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Productos solicitados')
                    ->description(fn (Order $record): string => $record->is_wholesale
                        ? 'Las cantidades de cada línea están expresadas en cajas (pedido al mayor).'
                        : 'Las cantidades de cada línea están expresadas en unidades (al detalle).')
                    ->icon(Heroicon::Cube)
                    ->extraAttributes([
                        'class' => 'fi-order-infolist-products-section',
                    ])
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('Líneas del pedido')
                            ->placeholder('No hay productos en este pedido.')
                            ->table([
                                TableColumn::make('Producto'),
                                TableColumn::make('Cantidad')
                                    ->width('10rem')
                                    ->alignment(Alignment::Center),
                                TableColumn::make('P. unitario')
                                    ->alignment(Alignment::End),
                                TableColumn::make('Total línea')
                                    ->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('product_name_snapshot')
                                    ->label('')
                                    ->formatStateUsing(function ($state, OrderItem $record): string {
                                        $name = filled($state) ? (string) $state : (string) ($record->product?->name ?? 'Producto');
                                        $sku = filled($record->sku_snapshot) ? (string) $record->sku_snapshot : '—';

                                        return $name.' · SKU: '.$sku;
                                    })
                                    ->weight('medium'),
                                TextEntry::make('quantity')
                                    ->label('')
                                    ->alignment(Alignment::Center)
                                    ->formatStateUsing(function ($state, OrderItem $record): string {
                                        $qty = rtrim(rtrim(number_format((float) $state, 3, ',', '.'), '0'), ',');
                                        $wholesale = $record->order?->is_wholesale ?? false;

                                        return $wholesale ? $qty.' cajas' : $qty.' uds.';
                                    }),
                                TextEntry::make('unit_price')
                                    ->label('')
                                    ->money()
                                    ->alignment(Alignment::End),
                                TextEntry::make('line_total')
                                    ->label('')
                                    ->money()
                                    ->weight('medium')
                                    ->alignment(Alignment::End),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Convenio')
                    ->description('Cobertura, referencia y notas del convenio.')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('convenio_type')
                                    ->label('Tipo de convenio')
                                    ->badge()
                                    ->formatStateUsing(fn (?ConvenioType $state): string => $state instanceof ConvenioType ? $state->label() : '—')
                                    ->color('gray')
                                    ->icon(Heroicon::Tag),
                                TextEntry::make('convenio_partner_name')
                                    ->label('Contraparte / aseguradora')
                                    ->icon(Heroicon::UserGroup)
                                    ->placeholder('—'),
                                TextEntry::make('convenio_reference')
                                    ->label('Referencia del convenio')
                                    ->icon(Heroicon::DocumentText)
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('convenio_notes')
                                    ->label('Notas del convenio')
                                    ->icon(Heroicon::ChatBubbleLeftRight)
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Entrega')
                    ->description('Destinatario, contacto, dirección y seguimiento.')
                    ->icon(Heroicon::Truck)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('delivery_recipient_name')
                                    ->label('Destinatario')
                                    ->icon(Heroicon::User)
                                    ->placeholder('—'),
                                TextEntry::make('delivery_phone')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::DevicePhoneMobile)
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('delivery_recipient_document')
                                    ->label('Cédula o RIF')
                                    ->icon(Heroicon::Identification)
                                    ->placeholder('—')
                                    ->copyable()
                                    ->visible(fn (Order $record): bool => filled($record->partner_company_id)),
                                TextEntry::make('delivery_address')
                                    ->label('Dirección')
                                    ->icon(Heroicon::MapPin)
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                TextEntry::make('delivery_city')
                                    ->label('Ciudad')
                                    ->icon(Heroicon::BuildingLibrary)
                                    ->placeholder('—'),
                                TextEntry::make('delivery_state')
                                    ->label('Estado / provincia')
                                    ->icon(Heroicon::Map)
                                    ->placeholder('—'),
                                TextEntry::make('delivery_notes')
                                    ->label('Notas de entrega')
                                    ->icon(Heroicon::ClipboardDocument)
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                TextEntry::make('scheduled_delivery_at')
                                    ->label('Entrega programada')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->icon(Heroicon::CalendarDays),
                                TextEntry::make('dispatched_at')
                                    ->label('Despachado')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->icon(Heroicon::PaperAirplane),
                                TextEntry::make('delivered_at')
                                    ->label('Entregado')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->icon(Heroicon::CheckCircle),
                                TextEntry::make('delivery_assignee')
                                    ->label('Responsable de entrega')
                                    ->icon(Heroicon::UserCircle)
                                    ->placeholder('—'),
                                ImageEntry::make('delivery_assignee_photo')
                                    ->label('Foto del repartidor')
                                    ->disk('public')
                                    ->height(140)
                                    ->imageHeight(140)
                                    ->circular()
                                    ->visible(fn (Order $record): bool => filled($record->deliveryAssigneeUser()?->delivery_photo_path))
                                    ->state(fn (Order $record): ?string => $record->deliveryAssigneeUser()?->delivery_photo_path)
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Totales')
                    ->description('Montos del pedido.')
                    ->icon(Heroicon::Calculator)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money()
                                    ->icon(Heroicon::Banknotes),
                                TextEntry::make('tax_total')
                                    ->label('Impuestos')
                                    ->money()
                                    ->icon(Heroicon::ReceiptPercent),
                                TextEntry::make('discount_total')
                                    ->label('Descuentos')
                                    ->money()
                                    ->icon(Heroicon::Tag),
                                TextEntry::make('total')
                                    ->label('Total')
                                    ->money()
                                    ->weight('bold')
                                    ->icon(Heroicon::CurrencyDollar),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Observaciones internas e historial del registro.')
                    ->icon(Heroicon::Clock)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('—')
                            ->icon(Heroicon::DocumentText)
                            ->columnSpanFull(),
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
