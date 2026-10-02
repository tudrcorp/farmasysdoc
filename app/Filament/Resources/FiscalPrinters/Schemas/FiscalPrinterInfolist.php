<?php

namespace App\Filament\Resources\FiscalPrinters\Schemas;

use App\Enums\FiscalPrinterMode;
use App\Enums\FiscalPrinterModel;
use App\Models\FiscalPrinter;
use App\Services\Fiscal\FiscalPrinterActivationChecklist;
use App\Support\Fiscal\FiscalPaymentCodes;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class FiscalPrinterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Activación')
                    ->description('Cada caja pasa a facturar con Farmadoc a su ritmo: Desactivada → Simulación → Activa.')
                    ->icon(Heroicon::RocketLaunch)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                TextEntry::make('mode')
                                    ->label('Modo')
                                    ->formatStateUsing(fn (mixed $state): string => FiscalPrinterMode::tryLabel($state))
                                    ->badge()
                                    ->color(fn (FiscalPrinter $record): string => $record->currentMode()->color())
                                    ->helperText(fn (FiscalPrinter $record): string => $record->currentMode()->description()),
                                TextEntry::make('mode_changed_at')
                                    ->label('Último cambio de modo')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('mode_changed_by')
                                    ->label('Cambiado por')
                                    ->placeholder('—'),
                            ]),
                        TextEntry::make('activation_checklist')
                            ->label('Lista de chequeo para «Activa»')
                            ->state(fn (FiscalPrinter $record): HtmlString => self::checklistHtml($record))
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Máquina fiscal')
                    ->icon(Heroicon::Printer)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nombre'),
                                TextEntry::make('model')
                                    ->label('Modelo')
                                    ->formatStateUsing(fn (mixed $state): string => FiscalPrinterModel::tryLabel($state)),
                                TextEntry::make('branch.name')
                                    ->label('Sucursal')
                                    ->placeholder('—'),
                                TextEntry::make('physicalCashBox.user.name')
                                    ->label('Caja (cajero)')
                                    ->placeholder('Sin caja asignada'),
                                TextEntry::make('serial_number')
                                    ->label('Nº de serial')
                                    ->copyable(),
                                TextEntry::make('fiscal_registry')
                                    ->label('Nº de registro SENIAT')
                                    ->copyable(),
                                TextEntry::make('connection_port')
                                    ->label('Puerto en la PC')
                                    ->placeholder('—'),
                                IconEntry::make('is_active')
                                    ->label('Activa')
                                    ->boolean(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Agente local')
                    ->description('Estado reportado por el agente de la PC de caja.')
                    ->icon(Heroicon::Signal)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('connection_state')
                                    ->label('Conexión')
                                    ->state(fn (FiscalPrinter $record): string => $record->isOnline() ? 'En línea' : 'Sin conexión')
                                    ->badge()
                                    ->color(fn (FiscalPrinter $record): string => $record->isOnline() ? 'success' : 'danger'),
                                TextEntry::make('last_heartbeat_at')
                                    ->label('Último contacto')
                                    ->since()
                                    ->dateTimeTooltip('d/m/Y H:i:s')
                                    ->placeholder('Nunca'),
                                TextEntry::make('agent_token_hash')
                                    ->label('Token del agente')
                                    ->state(fn (FiscalPrinter $record): string => filled($record->agent_token_hash) ? 'Configurado' : 'Sin token')
                                    ->badge()
                                    ->color(fn (FiscalPrinter $record): string => filled($record->agent_token_hash) ? 'success' : 'warning'),
                                TextEntry::make('agent_version')
                                    ->label('Versión del agente')
                                    ->placeholder('—'),
                                TextEntry::make('last_fiscal_number')
                                    ->label('Última factura fiscal')
                                    ->placeholder('—'),
                                TextEntry::make('last_z_number')
                                    ->label('Último reporte Z')
                                    ->placeholder('—'),
                                TextEntry::make('last_status')
                                    ->label('Último estado de la máquina')
                                    ->state(fn (FiscalPrinter $record): ?string => is_array($record->last_status) && $record->last_status !== []
                                        ? (string) json_encode($record->last_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                        : null)
                                    ->fontFamily('mono')
                                    ->extraAttributes(['class' => 'whitespace-pre-wrap'])
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Medios de pago de esta máquina')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema(collect(FiscalPaymentCodes::labels())
                                ->map(fn (string $label, string $code): TextEntry => TextEntry::make('payment_slot_'.$code)
                                    ->label($label)
                                    ->state(fn (FiscalPrinter $record): ?string => $record->payment_slots[$code] ?? null)
                                    ->badge()
                                    ->color(fn (?string $state): string => filled($state) ? 'gray' : 'danger')
                                    ->placeholder('Sin asignar'))
                                ->values()
                                ->all()),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    private static function checklistHtml(FiscalPrinter $record): HtmlString
    {
        $icons = [
            FiscalPrinterActivationChecklist::OK => ['✓', '#047857'],
            FiscalPrinterActivationChecklist::WARNING => ['!', '#b45309'],
            FiscalPrinterActivationChecklist::FAIL => ['✗', '#b91c1c'],
        ];

        $rows = collect(app(FiscalPrinterActivationChecklist::class)->evaluate($record))
            ->map(function (array $item) use ($icons): string {
                [$icon, $color] = $icons[$item['state']];

                return '<li style="display:flex;gap:.5rem;align-items:baseline;padding:.15rem 0">'
                    .'<span style="color:'.$color.';font-weight:700;width:1rem;text-align:center">'.$icon.'</span>'
                    .'<span><strong>'.e($item['label']).'</strong> — '.e($item['detail']).'</span></li>';
            })
            ->implode('');

        return new HtmlString('<ul style="margin:0;padding:0;list-style:none">'.$rows.'</ul>');
    }
}
