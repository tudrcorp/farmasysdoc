<?php

namespace App\Filament\Resources\FiscalPrinters\Schemas;

use App\Enums\FiscalPrinterModel;
use App\Models\Branch;
use App\Models\PhysicalCashBox;
use App\Support\Filament\BranchAuthScope;
use App\Support\Fiscal\FiscalPaymentCodes;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FiscalPrinterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Máquina fiscal')
                    ->description('Datos de la etiqueta del equipo y caja a la que está conectada. El agente local de esa PC imprime las facturas de esa caja.')
                    ->icon(Heroicon::Printer)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(80)
                                    ->placeholder('Ej. Caja 1 · Sucursal Centro')
                                    ->prefixIcon(Heroicon::Tag),
                                Select::make('model')
                                    ->label('Modelo')
                                    ->options(FiscalPrinterModel::options())
                                    ->default(FiscalPrinterModel::AclasPp9Plus->value)
                                    ->required()
                                    ->native(false)
                                    ->prefixIcon(Heroicon::Printer),
                                Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->options(fn (): array => Branch::query()
                                        ->where('is_active', true)
                                        ->tap(fn ($query) => BranchAuthScope::applyToBranchFormSelect($query))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->prefixIcon(Heroicon::BuildingStorefront)
                                    ->default(fn (): ?int => BranchAuthScope::suggestedBranchIdForOperationalForm()),
                                Select::make('physical_cash_box_id')
                                    ->label('Caja (cajero)')
                                    ->options(fn (): array => PhysicalCashBox::query()
                                        ->with('user:id,name,email')
                                        ->get()
                                        ->mapWithKeys(fn (PhysicalCashBox $box): array => [
                                            $box->id => ($box->user?->name ?? 'Caja #'.$box->id).($box->user?->email ? ' · '.$box->user->email : ''),
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->native(false)
                                    ->unique(ignoreRecord: true)
                                    ->validationMessages([
                                        'unique' => 'Esa caja ya tiene una máquina fiscal asignada.',
                                    ])
                                    ->prefixIcon(Heroicon::Banknotes)
                                    ->helperText('Solo las ventas de este cajero se imprimen en esta máquina. Sin caja asignada, la máquina no recibe facturas.'),
                                TextInput::make('serial_number')
                                    ->label('Nº de serial')
                                    ->required()
                                    ->maxLength(40)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('Ej. 3100020235')
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state)),
                                TextInput::make('fiscal_registry')
                                    ->label('Nº de registro SENIAT')
                                    ->required()
                                    ->maxLength(40)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('Ej. ZZP0020235-I')
                                    ->prefixIcon(Heroicon::ShieldCheck)
                                    ->dehydrateStateUsing(fn (?string $state): string => mb_strtoupper(trim((string) $state))),
                                TextInput::make('connection_port')
                                    ->label('Puerto en la PC')
                                    ->maxLength(40)
                                    ->placeholder('Ej. COM3')
                                    ->prefixIcon(Heroicon::Link)
                                    ->helperText('Puerto COM donde Windows ve la máquina (Administrador de dispositivos → Puertos).'),
                                Toggle::make('is_active')
                                    ->label('Máquina activa')
                                    ->helperText('Inactiva: no recibe documentos y su token deja de funcionar.')
                                    ->default(true)
                                    ->inline(false),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Medios de pago de esta máquina')
                    ->description('Número de medio de pago (01-24) programado en ESTA máquina para cada forma de cobro de Farmadoc. Cada máquina puede tener números distintos: tómelos de la configuración del sistema actual o del reporte Z (p. ej. «EFECTIVO (#09)»). Puede repetir un número si la máquina no distingue ese medio.')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema(collect(FiscalPaymentCodes::labels())
                                ->map(fn (string $label, string $code): TextInput => TextInput::make('payment_slots.'.$code)
                                    ->label($label)
                                    ->placeholder('Ej. 01')
                                    ->maxLength(2)
                                    ->regex('/^(0?[1-9]|1\d|2[0-4])$/')
                                    ->validationMessages([
                                        'regex' => 'Use un número entre 01 y 24.',
                                    ])
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? str_pad(trim($state), 2, '0', STR_PAD_LEFT) : null))
                                ->values()
                                ->all()),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }
}
