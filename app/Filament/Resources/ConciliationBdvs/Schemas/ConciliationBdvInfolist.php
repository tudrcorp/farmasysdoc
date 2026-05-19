<?php

namespace App\Filament\Resources\ConciliationBdvs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ConciliationBdvInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen de conciliación')
                    ->icon(Heroicon::ArrowsRightLeft)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('conciliated_at')
                                    ->label('Conciliado en')
                                    ->dateTime('d/m/Y H:i:s'),
                                TextEntry::make('branch.name')
                                    ->label('Sucursal'),
                                TextEntry::make('user.name')
                                    ->label('Conciliado por')
                                    ->placeholder('—'),
                                TextEntry::make('reference')
                                    ->label('Referencia')
                                    ->copyable(),
                                TextEntry::make('amount')
                                    ->label('Monto')
                                    ->money('VES'),
                                TextEntry::make('payment_date')
                                    ->label('Fecha de pago')
                                    ->date('d/m/Y'),
                                TextEntry::make('payer_document')
                                    ->label('Documento pagador'),
                                TextEntry::make('payer_phone')
                                    ->label('Teléfono pagador'),
                                TextEntry::make('destination_phone')
                                    ->label('Teléfono destino (comercio)'),
                                TextEntry::make('origin_bank')
                                    ->label('Banco origen')
                                    ->placeholder('—'),
                                TextEntry::make('environment')
                                    ->label('Entorno')
                                    ->badge(),
                                TextEntry::make('bdv_http_status')
                                    ->label('HTTP BDV')
                                    ->placeholder('—'),
                                TextEntry::make('bdv_code')
                                    ->label('Código BDV')
                                    ->placeholder('—'),
                                TextEntry::make('bdv_message')
                                    ->label('Mensaje BDV')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Payload enviado a BDV')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        TextEntry::make('bdv_payload')
                            ->hiddenLabel()
                            ->formatStateUsing(function (mixed $state): HtmlString {
                                $json = is_array($state)
                                    ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                    : null;

                                return new HtmlString(
                                    '<pre class="m-0 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-900/40">'.e($json ?: '{}').'</pre>'
                                );
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Respuesta BDV')
                    ->icon(Heroicon::Server)
                    ->schema([
                        TextEntry::make('bdv_response')
                            ->hiddenLabel()
                            ->formatStateUsing(function (mixed $state): HtmlString {
                                $json = is_array($state)
                                    ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                    : null;

                                return new HtmlString(
                                    '<pre class="m-0 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-900/40">'.e($json ?: '{}').'</pre>'
                                );
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
