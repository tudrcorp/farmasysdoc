<?php

namespace App\Filament\Resources\Deliveries\Schemas;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Delivery;
use App\Models\User;
use App\Support\Deliveries\DeliveryTypeLabels;
use App\Support\Orders\PartnerOrderDeliverySync;
use Carbon\Carbon;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DeliveryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen')
                    ->description('Identificación rápida de la entrega y su estado operativo.')
                    ->icon(Heroicon::Truck)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('Nº pedido')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->icon(Heroicon::ClipboardDocumentList)
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Número copiado')
                                    ->columnSpan(['default' => 1, 'sm' => 1, 'lg' => 1]),
                                TextEntry::make('status')
                                    ->label('Estado de la entrega')
                                    ->badge()
                                    ->size('lg')
                                    ->formatStateUsing(fn (?DeliveryStatus $state): string => $state instanceof DeliveryStatus ? $state->label() : '—')
                                    ->color(fn (?DeliveryStatus $state): string => $state instanceof DeliveryStatus ? $state->filamentColor() : 'gray')
                                    ->icon(Heroicon::Signal),
                                TextEntry::make('delivery_type')
                                    ->label('Tipo')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => DeliveryTypeLabels::label($state))
                                    ->color(fn (?string $state): string => match ($state) {
                                        PartnerOrderDeliverySync::DELIVERY_TYPE_PARTNER => 'info',
                                        PartnerOrderDeliverySync::DELIVERY_TYPE_CLIENT_ORDER => 'success',
                                        default => 'gray',
                                    })
                                    ->icon(Heroicon::Tag),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Ubicación y pedido')
                    ->description('Sucursal, vínculo al pedido y quién registró la entrega.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('branch.name')
                                    ->label('Sucursal')
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->placeholder('—')
                                    ->url(fn (Delivery $record): ?string => self::adminBranchUrl($record))
                                    ->openUrlInNewTab(false),
                                TextEntry::make('order_link')
                                    ->label('Pedido en el sistema')
                                    ->state(fn (Delivery $record): string => $record->order_id !== null
                                        ? 'Pedido #'.(string) $record->order_id
                                        : '—')
                                    ->icon(Heroicon::ShoppingCart)
                                    ->url(fn (Delivery $record): ?string => self::adminOrderUrl($record))
                                    ->openUrlInNewTab(false)
                                    ->placeholder('—'),
                                TextEntry::make('user.name')
                                    ->label('Usuario que registró')
                                    ->icon(Heroicon::User)
                                    ->placeholder('—'),
                                TextEntry::make('taken_by')
                                    ->label('Responsable de ruta / tomado por')
                                    ->icon(Heroicon::UserCircle)
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                ImageEntry::make('delivery_assignee_photo')
                                    ->label('Foto del repartidor')
                                    ->disk('public')
                                    ->height(140)
                                    ->imageHeight(140)
                                    ->circular()
                                    ->visible(fn (Delivery $record): bool => filled($record->user_id)
                                        && filled($record->user?->delivery_photo_path))
                                    ->state(fn (Delivery $record): ?string => $record->user?->delivery_photo_path)
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                ImageEntry::make('delivery_evidence_path')
                                    ->label('Evidencia de entrega')
                                    ->disk('public')
                                    ->height(220)
                                    ->imageHeight(220)
                                    ->visible(fn (Delivery $record): bool => filled($record->delivery_evidence_path))
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Datos del pedido al generar la entrega')
                    ->description('Copia de la información de envío y totales en el momento en que se creó o actualizó esta fila (útil para operaciones sin abrir el pedido).')
                    ->icon(Heroicon::ArchiveBox)
                    ->extraAttributes([
                        'class' => 'fi-delivery-infolist-snapshot-section',
                    ])
                    ->visible(fn (Delivery $record): bool => filled($record->order_snapshot) && is_array($record->order_snapshot))
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('snap_partner')
                                    ->label('Aliado')
                                    ->state(fn (Delivery $record): string => self::snapshotPartnerLine($record))
                                    ->icon(Heroicon::BuildingOffice2)
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                TextEntry::make('snap_recipient')
                                    ->label('Destinatario')
                                    ->state(fn (Delivery $record): string => self::snapshotString($record, 'delivery_recipient_name'))
                                    ->icon(Heroicon::User)
                                    ->placeholder('—'),
                                TextEntry::make('snap_phone')
                                    ->label('Teléfono')
                                    ->state(fn (Delivery $record): string => self::snapshotString($record, 'delivery_phone'))
                                    ->icon(Heroicon::Phone)
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('snap_recipient_document')
                                    ->label('Cédula o RIF')
                                    ->state(fn (Delivery $record): string => self::snapshotString($record, 'delivery_recipient_document'))
                                    ->icon(Heroicon::Identification)
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('snap_address')
                                    ->label('Dirección')
                                    ->state(fn (Delivery $record): string => self::snapshotString($record, 'delivery_address'))
                                    ->icon(Heroicon::MapPin)
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                TextEntry::make('snap_city_state')
                                    ->label('Ciudad / departamento')
                                    ->state(fn (Delivery $record): string => self::snapshotCityState($record))
                                    ->placeholder('—'),
                                TextEntry::make('snap_scheduled')
                                    ->label('Entrega programada')
                                    ->state(fn (Delivery $record): string => self::snapshotScheduled($record))
                                    ->icon(Heroicon::CalendarDays)
                                    ->placeholder('—'),
                                TextEntry::make('snap_total')
                                    ->label('Total pedido')
                                    ->state(fn (Delivery $record): string => ($v = self::snapshotNumeric($record, 'total')) !== null
                                        ? number_format($v, 2, ',', '.')
                                        : '—')
                                    ->icon(Heroicon::CurrencyDollar)
                                    ->placeholder('—'),
                                TextEntry::make('snap_items')
                                    ->label('Ítems')
                                    ->state(fn (Delivery $record): string => self::snapshotItemsLine($record))
                                    ->icon(Heroicon::Cube)
                                    ->placeholder('—'),
                                TextEntry::make('snap_order_status')
                                    ->label('Estado del pedido (snapshot)')
                                    ->badge()
                                    ->state(fn (Delivery $record): string => self::snapshotOrderStatusLabel($record))
                                    ->color(fn (Delivery $record): string => self::snapshotOrderStatusColor($record))
                                    ->placeholder('—'),
                                TextEntry::make('snap_notes')
                                    ->label('Notas de entrega')
                                    ->state(fn (Delivery $record): string => self::snapshotString($record, 'delivery_notes'))
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Fechas de creación y última modificación.')
                    ->icon(Heroicon::Clock)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Creado')
                                    ->dateTime('d/m/Y H:i')
                                    ->sinceTooltip()
                                    ->icon(Heroicon::PlusCircle),
                                TextEntry::make('updated_at')
                                    ->label('Actualizado')
                                    ->dateTime('d/m/Y H:i')
                                    ->sinceTooltip()
                                    ->icon(Heroicon::ArrowPath),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function adminOrderUrl(Delivery $record): ?string
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->isAdministrator() || $record->order_id === null) {
            return null;
        }

        return OrderResource::getUrl('view', ['record' => $record->order_id], isAbsolute: false);
    }

    private static function adminBranchUrl(Delivery $record): ?string
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->isAdministrator() || $record->branch_id === null) {
            return null;
        }

        return BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function snap(Delivery $record): ?array
    {
        $s = $record->order_snapshot;

        return is_array($s) ? $s : null;
    }

    private static function snapshotString(Delivery $record, string $key): string
    {
        $v = self::snap($record)[$key] ?? null;

        return filled($v) ? (string) $v : '—';
    }

    private static function snapshotNumeric(Delivery $record, string $key): ?float
    {
        $v = self::snap($record)[$key] ?? null;
        if ($v === null || $v === '') {
            return null;
        }

        return (float) $v;
    }

    private static function snapshotPartnerLine(Delivery $record): string
    {
        $s = self::snap($record);
        if ($s === null) {
            return '—';
        }
        $code = $s['partner_company_code'] ?? null;
        $id = $s['partner_company_id'] ?? null;
        if (filled($code) && filled($id)) {
            return (string) $code.' (ID '.(string) $id.')';
        }
        if (filled($id)) {
            return 'ID '.(string) $id;
        }

        return '—';
    }

    private static function snapshotCityState(Delivery $record): string
    {
        $s = self::snap($record);
        if ($s === null) {
            return '—';
        }
        $city = $s['delivery_city'] ?? '';
        $state = $s['delivery_state'] ?? '';
        $line = trim(implode(', ', array_filter([(string) $city, (string) $state])));

        return $line !== '' ? $line : '—';
    }

    private static function snapshotScheduled(Delivery $record): string
    {
        $s = self::snap($record);
        if ($s === null) {
            return '—';
        }
        $raw = $s['scheduled_delivery_at'] ?? null;
        if (blank($raw)) {
            return '—';
        }
        try {
            return Carbon::parse((string) $raw)
                ->timezone(config('app.timezone'))
                ->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $raw;
        }
    }

    private static function snapshotItemsLine(Delivery $record): string
    {
        $s = self::snap($record);
        if ($s === null) {
            return '—';
        }
        $n = $s['items_count'] ?? null;
        $wholesale = filter_var($s['is_wholesale'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($n === null || $n === '') {
            return '—';
        }
        $qty = is_numeric($n) ? (string) $n : (string) $n;

        return $wholesale ? $qty.' líneas (cantidades en cajas)' : $qty.' líneas (unidades)';
    }

    private static function snapshotOrderStatusLabel(Delivery $record): string
    {
        $s = self::snap($record);
        if ($s === null) {
            return '—';
        }
        $v = $s['status'] ?? null;
        if (! is_string($v) || $v === '') {
            return '—';
        }
        $enum = OrderStatus::tryFrom($v);

        return $enum instanceof OrderStatus ? $enum->label() : $v;
    }

    private static function snapshotOrderStatusColor(Delivery $record): string
    {
        $s = self::snap($record);
        if ($s === null) {
            return 'gray';
        }
        $v = $s['status'] ?? null;
        if (! is_string($v)) {
            return 'gray';
        }
        $enum = OrderStatus::tryFrom($v);

        return $enum instanceof OrderStatus ? $enum->filamentColor() : 'gray';
    }
}
