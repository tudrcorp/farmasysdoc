<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
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
                                    ->color(fn (?OrderStatus $state): string => match ($state) {
                                        OrderStatus::Pending => 'warning',
                                        OrderStatus::Confirmed => 'info',
                                        OrderStatus::Preparing => 'gray',
                                        OrderStatus::ReadyForDispatch => 'primary',
                                        OrderStatus::Dispatched => 'success',
                                        OrderStatus::InTransit => 'info',
                                        OrderStatus::Delivered => 'success',
                                        OrderStatus::Cancelled => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('client.name')
                                    ->label('Cliente')
                                    ->icon(Heroicon::User)
                                    ->placeholder('—')
                                    ->url(fn (Order $record): ?string => $record->client_id
                                        ? ClientResource::getUrl('view', ['record' => $record->client_id], isAbsolute: false)
                                        : null)
                                    ->openUrlInNewTab(false),
                                TextEntry::make('branch.name')
                                    ->label('Sucursal')
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->placeholder('—')
                                    ->url(fn (Order $record): ?string => $record->branch_id
                                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                                        : null)
                                    ->openUrlInNewTab(false),
                                TextEntry::make('partner_company_code')
                                    ->label('Código empresa aliada')
                                    ->icon(Heroicon::BuildingOffice2)
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ]),
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
