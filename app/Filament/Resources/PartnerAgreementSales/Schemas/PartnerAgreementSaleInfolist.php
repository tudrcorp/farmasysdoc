<?php

namespace App\Filament\Resources\PartnerAgreementSales\Schemas;

use App\Enums\PartnerAgreementSaleStatus;
use App\Models\PartnerAgreementSale;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PartnerAgreementSaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nota de entrega')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        Grid::make(['default' => 2, 'md' => 4])
                            ->schema([
                                TextEntry::make('number')->label('Número')->fontFamily('mono')->weight('bold'),
                                TextEntry::make('sold_at')->label('Fecha y hora')->dateTime('d/m/Y h:i a'),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->formatStateUsing(fn (PartnerAgreementSaleStatus $state): string => $state->label())
                                    ->color(fn (PartnerAgreementSaleStatus $state): string => $state->color()),
                                TextEntry::make('payment')->label('Forma de pago')->state(PartnerAgreementSale::PAYMENT_LABEL)->badge()->color('primary'),
                                TextEntry::make('branch.name')->label('Sucursal'),
                                TextEntry::make('seller.name')->label('Atendido por')->placeholder('—'),
                                TextEntry::make('emailed_at')->label('Enviada por correo')->dateTime('d/m/Y h:i a')->placeholder('No'),
                                TextEntry::make('whatsapped_at')->label('Enviada por WhatsApp')->dateTime('d/m/Y h:i a')->placeholder('No'),
                            ]),
                        TextEntry::make('cancellation')
                            ->hiddenLabel()
                            ->state(fn (PartnerAgreementSale $record): string => 'Anulada el '.$record->cancelled_at?->format('d/m/Y h:i a').' por '.($record->canceller?->name ?? '—').': '.$record->cancellation_reason)
                            ->color('danger')
                            ->icon(Heroicon::XCircle)
                            ->visible(fn (PartnerAgreementSale $record): bool => $record->isCancelled()),
                    ])
                    ->columnSpanFull(),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('Afiliado')
                            ->icon(Heroicon::User)
                            ->schema([
                                TextEntry::make('client_name')->label('Nombre')->weight('semibold'),
                                Grid::make(3)->schema([
                                    TextEntry::make('client_document')->label('Cédula')->placeholder('—'),
                                    TextEntry::make('client_phone')->label('Teléfono')->placeholder('—'),
                                    TextEntry::make('client_email')->label('Correo')->placeholder('—'),
                                ]),
                            ]),
                        Section::make('Convenio')
                            ->icon(Heroicon::BuildingOffice2)
                            ->schema([
                                TextEntry::make('partnerCompany.legal_name')->label('Aliado')->weight('semibold'),
                                Grid::make(3)->schema([
                                    TextEntry::make('plan.name')->label('Plan'),
                                    TextEntry::make('total')->label('Cargo al fondo')->money('USD')->weight('bold')->color('primary'),
                                    TextEntry::make('fund_balance_after_usd')->label('Disponible tras la venta')->money('USD')->placeholder('—'),
                                ]),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Productos')
                    ->icon(Heroicon::ShoppingBag)
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Producto'),
                                TableColumn::make('Cantidad')->alignEnd(),
                                TableColumn::make('Precio unit.')->alignEnd(),
                                TableColumn::make('IVA')->alignEnd(),
                                TableColumn::make('Importe')->alignEnd(),
                            ])
                            ->schema([
                                TextEntry::make('product_name_snapshot'),
                                TextEntry::make('quantity')->numeric(decimalPlaces: 0, locale: 'es')->alignEnd(),
                                TextEntry::make('unit_price')->money('USD')->alignEnd(),
                                TextEntry::make('tax_amount')->money('USD')->alignEnd(),
                                TextEntry::make('line_total')->money('USD')->weight('semibold')->alignEnd(),
                            ]),
                        Grid::make(['default' => 3, 'md' => 6])
                            ->schema([
                                TextEntry::make('subtotal')->label('Subtotal')->money('USD')->columnStart(['md' => 4]),
                                TextEntry::make('tax_total')->label('IVA')->money('USD'),
                                TextEntry::make('total')->label('Total')->money('USD')->weight('bold')->size('lg'),
                            ]),
                        TextEntry::make('total_ves')
                            ->label('Equivalente en bolívares')
                            ->formatStateUsing(fn (mixed $state, PartnerAgreementSale $record): string => 'Bs. '.number_format((float) $state, 2, ',', '.').' (tasa '.number_format((float) $record->bcv_ves_per_usd, 2, ',', '.').')')
                            ->visible(fn (PartnerAgreementSale $record): bool => $record->total_ves !== null),
                        TextEntry::make('notes')->label('Observaciones')->placeholder('—'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
