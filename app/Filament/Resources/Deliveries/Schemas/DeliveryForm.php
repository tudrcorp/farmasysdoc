<?php

namespace App\Filament\Resources\Deliveries\Schemas;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Support\Deliveries\DeliveryTypeLabels;
use App\Support\Filament\BranchAuthScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class DeliveryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vínculo con el pedido')
                    ->description('Asocie la entrega a un pedido del sistema. El número de pedido se puede completar solo al guardar.')
                    ->icon(Heroicon::ShoppingCart)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('order_id')
                                    ->label('Pedido')
                                    ->relationship(
                                        name: 'order',
                                        titleAttribute: 'order_number',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderByDesc('id'),
                                    )
                                    ->getOptionLabelFromRecordUsing(function (Order $record): string {
                                        $num = filled($record->order_number) ? (string) $record->order_number : 'Sin número';

                                        return $num.' · ID '.$record->id;
                                    })
                                    ->searchable(['order_number', 'id'])
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Seleccione un pedido')
                                    ->helperText('Busque por número de pedido o por ID interno.')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                TextInput::make('order_number')
                                    ->label('Número de pedido (referencia)')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->helperText('Opcional: si deja vacío y eligió pedido arriba, se rellena automáticamente al guardar.'),
                                Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->relationship(
                                        name: 'branch',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query): Builder {
                                            $query->where('is_active', true)->orderBy('name');

                                            return BranchAuthScope::applyToBranchFormSelect($query);
                                        },
                                    )
                                    ->default(fn (): ?int => BranchAuthScope::suggestedBranchIdForOperationalForm())
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Sin sucursal')
                                    ->prefixIcon(Heroicon::BuildingStorefront),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Operación de entrega')
                    ->description('Tipo, estado y responsables.')
                    ->icon(Heroicon::Truck)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('delivery_type')
                                    ->label('Tipo de entrega')
                                    ->options(DeliveryTypeLabels::formOptions())
                                    ->native(false)
                                    ->searchable()
                                    ->required()
                                    ->default(DeliveryTypeLabels::TYPE_MANUAL)
                                    ->helperText('Las generadas desde pedidos aliado usan «Aliado · envío a domicilio».')
                                    ->prefixIcon(Heroicon::Tag),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options(DeliveryStatus::options())
                                    ->native(false)
                                    ->required()
                                    ->default(DeliveryStatus::Pending->value)
                                    ->prefixIcon(Heroicon::Signal),
                                Select::make('user_id')
                                    ->label('Usuario que registra')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Sin asignar')
                                    ->prefixIcon(Heroicon::User),
                                TextInput::make('taken_by')
                                    ->label('Responsable de ruta / tomado por')
                                    ->maxLength(255)
                                    ->placeholder('Nombre del repartidor o flota')
                                    ->prefixIcon(Heroicon::UserCircle)
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
