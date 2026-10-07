<?php

namespace App\Filament\Resources\FiscalDocuments\Schemas;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\FiscalDocument;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FiscalDocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Documento')
                    ->icon(Heroicon::ReceiptPercent)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                TextEntry::make('type')
                                    ->label('Tipo')
                                    ->formatStateUsing(fn (mixed $state): string => FiscalDocumentType::tryLabel($state)),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->formatStateUsing(fn (mixed $state): string => FiscalDocumentStatus::tryLabel($state))
                                    ->badge()
                                    ->color(fn (FiscalDocument $record): string => $record->status->color()),
                                TextEntry::make('simulation')
                                    ->label('Origen')
                                    ->state(fn (FiscalDocument $record): string => match (true) {
                                        $record->is_test && $record->simulation => 'Laboratorio · simulación (no se imprime)',
                                        $record->is_test => 'Laboratorio fiscal (prueba)',
                                        $record->simulation => 'Simulación de venta (no se imprime)',
                                        default => 'Documento real',
                                    })
                                    ->badge()
                                    ->color(fn (FiscalDocument $record): string => match (true) {
                                        $record->is_test => 'warning',
                                        $record->simulation => 'info',
                                        default => 'success',
                                    }),
                                TextEntry::make('fiscal_number')
                                    ->label('Nº fiscal')
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('sale.sale_number')
                                    ->label('Venta')
                                    ->url(fn (FiscalDocument $record): ?string => $record->sale
                                        ? SaleResource::getUrl('view', ['record' => $record->sale])
                                        : null)
                                    ->placeholder('—'),
                                TextEntry::make('fiscalPrinter.name')
                                    ->label('Máquina fiscal'),
                                TextEntry::make('printer_serial')
                                    ->label('Serial reportado')
                                    ->placeholder('—'),
                                TextEntry::make('z_number')
                                    ->label('Nº de Z')
                                    ->placeholder('—'),
                                TextEntry::make('expected_total_ves')
                                    ->label('Total esperado (Bs)')
                                    ->numeric(2, ',', '.')
                                    ->placeholder('—'),
                                TextEntry::make('printer_total_ves')
                                    ->label('Total máquina (Bs)')
                                    ->numeric(2, ',', '.')
                                    ->placeholder('—'),
                                TextEntry::make('payload.expected.sale_total_ves')
                                    ->label('Total de la venta (Bs, referencia)')
                                    ->helperText('Venta en USD × tasa BCV; puede diferir en céntimos del total fiscal por redondeo.')
                                    ->numeric(2, ',', '.')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Trazabilidad')
                    ->icon(Heroicon::Clock)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                TextEntry::make('created_at')->label('Encolado')->dateTime('d/m/Y H:i:s'),
                                TextEntry::make('claimed_at')->label('Tomado por el agente')->dateTime('d/m/Y H:i:s')->placeholder('—'),
                                TextEntry::make('printed_at')->label('Resultado recibido')->dateTime('d/m/Y H:i:s')->placeholder('—'),
                                TextEntry::make('printer_datetime')->label('Fecha en la máquina')->dateTime('d/m/Y H:i:s')->placeholder('—'),
                                TextEntry::make('printer_counter_before')->label('Contador antes de imprimir')->placeholder('—'),
                                TextEntry::make('attempts')->label('Intentos'),
                                TextEntry::make('requested_by')->label('Solicitado por')->placeholder('—'),
                                TextEntry::make('resolved_by')->label('Resuelto por')->placeholder('—'),
                                TextEntry::make('uuid')->label('Clave de idempotencia')->copyable(),
                                TextEntry::make('error_code')->label('Código de error')->placeholder('—'),
                                TextEntry::make('error_message')
                                    ->label('Mensaje')
                                    ->extraAttributes(['class' => 'whitespace-pre-wrap'])
                                    ->placeholder('—')
                                    ->columnSpan(2),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Respuesta del agente')
                    ->description(fn (FiscalDocument $record): string => $record->simulation
                        ? 'Simulación: comandos que el agente habría enviado a la máquina fiscal (no se imprimió nada).'
                        : 'Datos técnicos reportados por el agente.')
                    ->icon(Heroicon::CommandLine)
                    ->collapsed(fn (FiscalDocument $record): bool => ! $record->simulation)
                    ->visible(fn (FiscalDocument $record): bool => filled($record->raw_response))
                    ->schema([
                        TextEntry::make('raw_response')
                            ->hiddenLabel()
                            ->state(fn (FiscalDocument $record): string => (string) json_encode(
                                $record->raw_response,
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            ))
                            ->fontFamily('mono')
                            ->extraAttributes(['class' => 'whitespace-pre-wrap']),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos enviados al agente')
                    ->icon(Heroicon::CodeBracket)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('payload')
                            ->hiddenLabel()
                            ->state(fn (FiscalDocument $record): string => (string) json_encode(
                                $record->payload,
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            ))
                            ->fontFamily('mono')
                            ->extraAttributes(['class' => 'whitespace-pre-wrap']),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
