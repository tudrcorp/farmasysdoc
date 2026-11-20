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
use App\Models\OrderAcceptanceItem;
use App\Models\OrderItem;
use App\Models\User;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

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
                                TextEntry::make('partner_zelle_reference_name')
                                    ->label('Nombre (Zelle)')
                                    ->icon(Heroicon::Envelope)
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Copiado')
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
                                TextEntry::make('partner_cash_payment_proof_path')
                                    ->label('Comprobante de pago')
                                    ->icon(Heroicon::DocumentArrowDown)
                                    ->placeholder('—')
                                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Ver / descargar' : '—')
                                    ->url(fn (Order $record): ?string => filled($record->partner_cash_payment_proof_path)
                                        ? Storage::disk('public')->url($record->partner_cash_payment_proof_path)
                                        : null)
                                    ->openUrlInNewTab()
                                    ->visible(fn (Order $record): bool => filled($record->partner_company_id)
                                        && $record->partner_payment_terms === OrderPartnerPaymentTerms::Cash
                                        && filled($record->partner_cash_payment_proof_path)),
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

                Section::make('Decisión del pedido')
                    ->description(function (Order $record): string {
                        $acceptance = $record->acceptance;
                        $rate = number_format((float) ($acceptance?->bcv_ves_per_usd ?? 0), 2, ',', '.');
                        $acceptedAt = $acceptance?->accepted_at?->timezone(config('app.timezone'));

                        return 'Nota '.($acceptance?->delivery_note_number ?? '—')
                            .' · tasa BCV '.$rate.' Bs. por USD'
                            .' · aceptado el '.($acceptedAt?->format('d/m/Y h:i a') ?? '—');
                    })
                    ->icon(Heroicon::ClipboardDocumentCheck)
                    ->visible(fn (Order $record): bool => $record->acceptance !== null)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                TextEntry::make('acceptance.accepted_total_usd')
                                    ->label('Total aceptado USD')
                                    ->state(fn (Order $record): string => 'USD '.number_format((float) ($record->acceptance?->accepted_total_usd ?? 0), 2, ',', '.'))
                                    ->weight('medium'),
                                TextEntry::make('acceptance.accepted_total_ves')
                                    ->label('Total aceptado VES')
                                    ->state(fn (Order $record): string => 'Bs. '.number_format((float) ($record->acceptance?->accepted_total_ves ?? 0), 2, ',', '.'))
                                    ->weight('medium'),
                                TextEntry::make('acceptance.accepted_by_name')
                                    ->label('Aceptado por')
                                    ->placeholder('—'),
                            ]),
                        RepeatableEntry::make('acceptance_items')
                            ->hiddenLabel()
                            ->state(fn (Order $record) => $record->acceptance?->items ?? [])
                            ->placeholder('Sin líneas registradas.')
                            ->table([
                                TableColumn::make('Medicamento'),
                                TableColumn::make('Pedido')->alignment(Alignment::Center),
                                TableColumn::make('Existencia')->alignment(Alignment::Center),
                                TableColumn::make('Decisión')->alignment(Alignment::Center),
                                TableColumn::make('Total USD')->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('product_name')
                                    ->formatStateUsing(function ($state, OrderAcceptanceItem $record): string {
                                        $name = filled($state) ? (string) $state : 'Producto';
                                        $barcode = filled($record->barcode) ? (string) $record->barcode : 'sin código';
                                        $note = $record->accepted ? '' : ' · '.$record->rejection_note;

                                        return $name.' · '.$barcode.$note;
                                    }),
                                TextEntry::make('quantity_ordered')
                                    ->alignment(Alignment::Center)
                                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 3, ',', '.'), '0'), ',')),
                                TextEntry::make('quantity_on_hand')
                                    ->alignment(Alignment::Center)
                                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 3, ',', '.'), '0'), ',')),
                                TextEntry::make('accepted')
                                    ->alignment(Alignment::Center)
                                    ->badge()
                                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Se envía' : 'No se envía')
                                    ->color(fn (?bool $state): string => $state ? 'success' : 'danger'),
                                TextEntry::make('line_total_usd')
                                    ->alignment(Alignment::End)
                                    ->formatStateUsing(function ($state, OrderAcceptanceItem $record): string {
                                        if (! $record->accepted) {
                                            return '—';
                                        }

                                        return 'USD '.number_format((float) $state, 2, ',', '.');
                                    }),
                            ])
                            ->columnSpanFull(),
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
                    ->visible(fn (Order $record): bool => ! ($enableAdminResourceLinks && $record->partner_prices_by_cost_profit))
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

                Section::make('Cobro del pedido')
                    ->description(function (Order $record): string {
                        $percent = number_format((float) $record->partner_pricing_profit_percent, 2, ',', '.');
                        $people = number_format((int) $record->partner_pricing_affiliates_count, 0, ',', '.');

                        return 'Plan '.$record->partner_pricing_plan_name
                            .' · tramo '.$record->partner_pricing_band_label
                            .' · '.$people.' afiliados · ganancia '.$percent.' % sobre el costo.';
                    })
                    ->icon(Heroicon::Banknotes)
                    ->visible(fn (Order $record): bool => $enableAdminResourceLinks && $record->partner_prices_by_cost_profit)
                    ->schema([
                        RepeatableEntry::make('pricing_items')
                            ->label('Detalle por medicamento')
                            ->state(fn (Order $record) => $record->items)
                            ->table([
                                TableColumn::make('Medicamento'),
                                TableColumn::make('Cant.')->alignment(Alignment::Center),
                                TableColumn::make('Costo')->alignment(Alignment::End),
                                TableColumn::make('%')->alignment(Alignment::End),
                                TableColumn::make('Ganancia')->alignment(Alignment::End),
                                TableColumn::make('Precio')->alignment(Alignment::End),
                                TableColumn::make('Total')->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('product_name_snapshot')
                                    ->formatStateUsing(function ($state, OrderItem $record): string {
                                        $name = filled($state) ? (string) $state : (string) ($record->product?->name ?? 'Producto');
                                        $sku = filled($record->sku_snapshot) ? (string) $record->sku_snapshot : '';

                                        return $sku === '' ? $name : $name.' · '.$sku;
                                    }),
                                TextEntry::make('quantity')
                                    ->alignment(Alignment::Center)
                                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 3, ',', '.'), '0'), ',')),
                                TextEntry::make('unit_cost')
                                    ->alignment(Alignment::End)
                                    ->formatStateUsing(fn ($state): string => 'USD '.number_format((float) $state, 2, ',', '.')),
                                TextEntry::make('profit_percent')
                                    ->alignment(Alignment::End)
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' %'),
                                TextEntry::make('line_profit')
                                    ->alignment(Alignment::End)
                                    ->state(function (OrderItem $record): string {
                                        $gain = ((float) $record->unit_price - (float) $record->unit_cost) * (float) $record->quantity;

                                        return 'USD '.number_format($gain, 2, ',', '.');
                                    }),
                                TextEntry::make('unit_price')
                                    ->alignment(Alignment::End)
                                    ->formatStateUsing(fn ($state): string => 'USD '.number_format((float) $state, 2, ',', '.')),
                                TextEntry::make('line_total')
                                    ->alignment(Alignment::End)
                                    ->weight('medium')
                                    ->formatStateUsing(fn ($state): string => 'USD '.number_format((float) $state, 2, ',', '.')),
                            ])
                            ->columnSpanFull(),
                        TextEntry::make('pricing_totals')
                            ->hiddenLabel()
                            ->state(function (Order $record): string {
                                $cost = 0.0;
                                $gain = 0.0;

                                foreach ($record->items as $item) {
                                    $cost += (float) $item->unit_cost * (float) $item->quantity;
                                    $gain += ((float) $item->unit_price - (float) $item->unit_cost) * (float) $item->quantity;
                                }

                                return 'Costo USD '.number_format($cost, 2, ',', '.')
                                    .' · Ganancia USD '.number_format($gain, 2, ',', '.')
                                    .' · Total del pedido USD '.number_format((float) $record->total, 2, ',', '.');
                            })
                            ->weight('medium')
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
                    ->extraAttributes([
                        'class' => 'fi-order-infolist-delivery-section',
                    ])
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->extraAttributes([
                                'class' => 'fi-order-infolist-delivery-fields-grid',
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
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->extraAttributes([
                                'class' => 'fi-order-infolist-delivery-dates-row',
                            ])
                            ->schema([
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
                                    ->label($enableAdminResourceLinks ? 'Fecha de entrega' : 'Entregado')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->icon(Heroicon::CheckCircle)
                                    ->helperText($enableAdminResourceLinks
                                        ? 'Registrada al cerrar la entrega con evidencia en el módulo Entregas.'
                                        : null),
                            ]),
                        Fieldset::make('Repartidor asignado')
                            ->columns(1)
                            ->extraAttributes([
                                'class' => 'fi-order-infolist-delivery-assignee-fieldset',
                            ])
                            ->visible(fn (Order $record): bool => filled($record->delivery_assignee)
                                || $record->deliveryAssigneeUser()?->hasAvatar() === true)
                            ->schema([
                                TextEntry::make('delivery_assignee')
                                    ->label('Nombre en ruta')
                                    ->icon(Heroicon::UserCircle)
                                    ->placeholder('—')
                                    ->weight('medium')
                                    ->columnSpanFull(),
                                ImageEntry::make('delivery_assignee_photo')
                                    ->label('Foto del repartidor')
                                    ->height(200)
                                    ->imageHeight(200)
                                    ->circular()
                                    ->alignment(Alignment::Center)
                                    ->visible(fn (Order $record): bool => $record->deliveryAssigneeUser()?->hasAvatar() === true)
                                    ->state(fn (Order $record): ?string => $record->deliveryAssigneeUser()?->avatarUrl())
                                    ->extraEntryWrapperAttributes([
                                        'class' => 'fi-order-infolist-delivery-assignee-photo-entry',
                                    ])
                                    ->extraImgAttributes([
                                        'class' => 'fi-order-infolist-delivery-assignee-img',
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Fieldset::make('Evidencia de entrega')
                            ->columns(1)
                            ->extraAttributes([
                                'class' => 'fi-order-infolist-delivery-evidence-fieldset',
                            ])
                            ->visible(fn (Order $record): bool => $enableAdminResourceLinks
                                && filled($record->partnerDeliveryEvidencePath()))
                            ->schema([
                                ImageEntry::make('partner_delivery_evidence_path')
                                    ->label('Fotografía en destino')
                                    ->helperText('Registrada por el usuario de entregas al confirmar la entrega con evidencia.')
                                    ->disk('public')
                                    ->circular(false)
                                    ->alignment(Alignment::Center)
                                    ->state(fn (Order $record): ?string => $record->partnerDeliveryEvidencePath())
                                    ->imageHeight(400)
                                    ->extraEntryWrapperAttributes([
                                        'class' => 'fi-order-infolist-delivery-evidence-entry',
                                    ])
                                    ->extraImgAttributes([
                                        'class' => 'fi-order-infolist-delivery-evidence-img',
                                    ])
                                    ->columnSpanFull(),
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
