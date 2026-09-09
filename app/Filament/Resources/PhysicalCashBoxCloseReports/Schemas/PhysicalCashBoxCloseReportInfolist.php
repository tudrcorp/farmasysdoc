<?php

namespace App\Filament\Resources\PhysicalCashBoxCloseReports\Schemas;

use App\Models\PhysicalCashBoxCloseReport;
use App\Support\Cash\PhysicalCashBoxCloseVariance;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

class PhysicalCashBoxCloseReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Turno')
                    ->icon(Heroicon::Clock)
                    ->schema([
                        TextEntry::make('branch.name')
                            ->label('Sucursal')
                            ->placeholder('Sin sucursal'),
                        TextEntry::make('user.name')
                            ->label('Cajero'),
                        TextEntry::make('opened_at')
                            ->label('Apertura')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('closed_at')
                            ->label('Cierre')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('has_mismatch')
                            ->label('Estado general')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => (bool) $state ? 'Descuadre' : 'Cuadrado')
                            ->color(fn (mixed $state): string => (bool) $state ? 'danger' : 'success'),
                    ])
                    ->columns(3),
                Section::make('Efectivo en caja física')
                    ->description('Comparación del efectivo declarado por el cajero contra el saldo esperado por el sistema.')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        TextEntry::make('expected_usd')
                            ->label('USD sistema')
                            ->money('USD'),
                        TextEntry::make('declared_usd')
                            ->label('USD declarado')
                            ->money('USD'),
                        TextEntry::make('difference_usd')
                            ->label('Diferencia USD')
                            ->color(fn (mixed $state): string => PhysicalCashBoxCloseVariance::isMismatch((float) $state) ? 'danger' : 'success')
                            ->formatStateUsing(function (mixed $state, PhysicalCashBoxCloseReport $record): string {
                                $amount = number_format((float) $state, 2);
                                $label = PhysicalCashBoxCloseVariance::statusLabel((float) $record->difference_usd);

                                return '$ '.$amount.' · '.$label;
                            }),
                        TextEntry::make('expected_ves')
                            ->label('VES sistema')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('Bs. '),
                        TextEntry::make('declared_ves')
                            ->label('VES declarado')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('Bs. '),
                        TextEntry::make('difference_ves')
                            ->label('Diferencia VES')
                            ->color(fn (mixed $state): string => PhysicalCashBoxCloseVariance::isMismatch((float) $state) ? 'danger' : 'success')
                            ->formatStateUsing(function (mixed $state, PhysicalCashBoxCloseReport $record): string {
                                $amount = number_format((float) $state, 2, ',', '.');
                                $label = PhysicalCashBoxCloseVariance::statusLabel((float) $record->difference_ves);

                                return 'Bs. '.$amount.' · '.$label;
                            }),
                    ])
                    ->columns(3),
                Section::make('Punto de venta por banco')
                    ->description('El sistema compara lo declarado por banco contra las ventas de esta caja cobradas en el punto de ese banco.')
                    ->icon(Heroicon::CreditCard)
                    ->schema([
                        RepeatableEntry::make('pos_lines')
                            ->hiddenLabel()
                            ->placeholder('Sin declaraciones ni cobros de punto de venta.')
                            ->table([
                                TableColumn::make('Banco'),
                                TableColumn::make('Sistema')
                                    ->alignment(Alignment::End),
                                TableColumn::make('Declarado')
                                    ->alignment(Alignment::End),
                                TableColumn::make('Diferencia')
                                    ->alignment(Alignment::End),
                                TableColumn::make('Estado'),
                            ])
                            ->schema([
                                TextEntry::make('bank_label')
                                    ->label('Banco'),
                                TextEntry::make('system_ves')
                                    ->label('Sistema')
                                    ->numeric(decimalPlaces: 2)
                                    ->prefix('Bs. '),
                                TextEntry::make('declared_ves')
                                    ->label('Declarado')
                                    ->numeric(decimalPlaces: 2)
                                    ->prefix('Bs. '),
                                TextEntry::make('difference_ves')
                                    ->label('Diferencia')
                                    ->numeric(decimalPlaces: 2)
                                    ->prefix('Bs. ')
                                    ->color(fn (mixed $state): string => PhysicalCashBoxCloseVariance::isMismatch((float) $state) ? 'danger' : 'success'),
                                TextEntry::make('status_label')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (mixed $state): string => in_array((string) $state, ['Faltante', 'Sobrante'], true) ? 'danger' : 'success'),
                            ]),
                    ]),
                Section::make('Envío')
                    ->description('El reporte se guarda primero. WhatsApp y correo se intentan después; si fallan, esta ficha sigue disponible.')
                    ->icon(Heroicon::PaperAirplane)
                    ->schema([
                        TextEntry::make('whatsapp_sent_at')
                            ->label('WhatsApp')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('No enviado')
                            ->color(fn (PhysicalCashBoxCloseReport $record): string => $record->whatsapp_sent_at !== null ? 'success' : 'danger'),
                        TextEntry::make('whatsapp_error')
                            ->label('Detalle WhatsApp')
                            ->placeholder('—')
                            ->color('danger'),
                        TextEntry::make('email_sent_at')
                            ->label('Correo')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('No enviado')
                            ->color(fn (PhysicalCashBoxCloseReport $record): string => $record->email_sent_at !== null ? 'success' : 'danger'),
                        TextEntry::make('email_error')
                            ->label('Detalle correo')
                            ->placeholder('—')
                            ->color('danger'),
                    ])
                    ->columns(2),
            ]);
    }
}
