<?php

namespace App\Filament\Resources\Deliveries\Tables;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Support\Deliveries\DeliveryTypeLabels;
use App\Support\Deliveries\MarkDeliveryInProgress;
use App\Support\Orders\PartnerOrderDeliverySync;
use App\Support\Partners\InsufficientPartnerCreditException;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class DeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'fi-ta-deliveries-table',
            ], merge: true)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['branch', 'order', 'user']))
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nº pedido')
                    ->description(fn (Delivery $record): ?string => filled($record->order?->partner_company_code)
                        ? 'Aliado: '.$record->order->partner_company_code
                        : null)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->weight('medium')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->iconColor('primary')
                    ->color('primary')
                    ->tooltip('Pulse para ver dirección y datos de entrega del pedido aliado')
                    ->action(self::viewDeliveryOrderPartnerInfoAction())
                    ->extraCellAttributes([
                        'class' => 'fi-ta-cell-delivery-order-modal-trigger',
                    ]),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?DeliveryStatus $state): string => $state instanceof DeliveryStatus ? $state->label() : '—')
                    ->color(fn (?DeliveryStatus $state): string => $state instanceof DeliveryStatus ? $state->filamentColor() : 'gray')
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::Signal)
                    ->alignment(Alignment::Center),
                TextColumn::make('delivery_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => DeliveryTypeLabels::label($state))
                    ->color(fn (?string $state): string => match ($state) {
                        PartnerOrderDeliverySync::DELIVERY_TYPE_PARTNER => 'info',
                        DeliveryTypeLabels::TYPE_MANUAL => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray')
                    ->tooltip(fn (Delivery $record): string => (string) ($record->branch?->name ?? 'Sin sucursal')),
                TextColumn::make('taken_by')
                    ->label('Responsable / ruta')
                    ->placeholder('—')
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->icon(Heroicon::UserCircle)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Registrado por')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::User)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('order.id')
                    ->label('ID pedido')
                    ->numeric()
                    ->sortable()
                    ->searchable()
                    ->alignment(Alignment::Center)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->sinceTooltip()
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->sinceTooltip()
                    ->icon(Heroicon::ArrowPath)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->recordUrl(fn (Delivery $record): string => DeliveryResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->emptyStateHeading('Sin entregas registradas')
            ->emptyStateDescription('Las entregas generadas por pedidos aliado con envío a domicilio aparecerán aquí. También puede registrar una entrega manualmente.')
            ->emptyStateIcon(Heroicon::Truck)
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(DeliveryStatus::options())
                    ->native(false)
                    ->multiple(),
                SelectFilter::make('delivery_type')
                    ->label('Tipo de entrega')
                    ->options([
                        ...DeliveryTypeLabels::filterOptions(),
                        DeliveryTypeLabels::TYPE_MANUAL => 'Registro manual',
                    ])
                    ->native(false),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship(
                        'branch',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(2)
            ->recordActions([
                Action::make('markDeliveryInProgress')
                    ->label('Iniciar entrega')
                    ->icon(Heroicon::PlayCircle)
                    ->color('warning')
                    ->visible(fn (Delivery $record): bool => self::canMarkDeliveryInProgress($record))
                    ->modalHeading('Poner entrega en proceso')
                    ->modalDescription('El pedido vinculado pasará a «En proceso» para que el aliado vea el estado actualizado. Su nombre quedará en «Responsable / ruta» y en el pedido como asignado a entrega.')
                    ->modalSubmitActionLabel('Confirmar')
                    ->requiresConfirmation()
                    ->successNotificationTitle('Entrega en proceso')
                    ->action(function (Delivery $record, Action $action): void {
                        $user = auth()->user();
                        if (! $user instanceof User) {
                            Notification::make()
                                ->danger()
                                ->title('Debe iniciar sesión.')
                                ->send();
                            $action->halt();
                        }
                        try {
                            MarkDeliveryInProgress::execute($record, $user);
                        } catch (InsufficientPartnerCreditException $e) {
                            Notification::make()
                                ->danger()
                                ->title($e->getMessage())
                                ->send();
                            $action->halt();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->danger()
                                ->title($e->getMessage())
                                ->send();
                            $action->halt();
                        }
                    }),
                ViewAction::make()
                    ->label('Ver')
                    ->icon(Heroicon::Eye),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ]);
    }

    private static function canMarkDeliveryInProgress(Delivery $record): bool
    {
        if ($record->status !== DeliveryStatus::Pending) {
            return false;
        }

        if ($record->order_id === null) {
            return false;
        }

        $record->loadMissing('order');

        if (! $record->order instanceof Order) {
            return false;
        }

        return $record->order->status !== OrderStatus::Completed;
    }

    /**
     * Modal estilo iOS: datos del pedido aliado (destinatario y dirección) al pulsar el Nº pedido.
     */
    public static function viewDeliveryOrderPartnerInfoAction(): Action
    {
        return Action::make('viewDeliveryOrderPartnerInfo')
            ->label('Datos de entrega del pedido')
            ->modalHeading(fn (Delivery $record): string => 'Pedido '.(filled($record->order_number) ? (string) $record->order_number : '—'))
            ->modalDescription(fn (Delivery $record): string => self::deliveryOrderPartnerModalDescription($record))
            ->modalIcon(Heroicon::MapPin)
            ->modalIconColor('primary')
            ->modalContent(function (Delivery $record): View {
                return view('filament.tables.delivery-order-partner-ios-modal', [
                    'sections' => self::buildDeliveryOrderPartnerModalSections($record),
                ]);
            })
            ->modalWidth(Width::Large)
            ->slideOver()
            ->modalSubmitAction(false)
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Listo')
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]))
            ->extraModalWindowAttributes([
                'class' => 'fi-ios-order-items-modal-window',
            ]);
    }

    private static function deliveryOrderPartnerModalDescription(Delivery $record): string
    {
        $record->loadMissing(['order']);

        if ($record->order instanceof Order) {
            return 'Datos actuales del pedido vinculado: persona de contacto y dirección de entrega.';
        }

        if (is_array($record->order_snapshot) && $record->order_snapshot !== []) {
            return 'Información del pedido del aliado guardada al crear o actualizar esta entrega.';
        }

        return 'No hay pedido vinculado ni copia de datos de envío.';
    }

    /**
     * @return list<array{title: string, rows: list<array{label: string, value: string}>}>
     */
    private static function buildDeliveryOrderPartnerModalSections(Delivery $delivery): array
    {
        $delivery->loadMissing(['order']);

        if ($delivery->order instanceof Order) {
            $delivery->order->loadCount('items');

            return self::deliveryOrderSectionsFromOrder($delivery->order);
        }

        $snap = $delivery->order_snapshot;
        if (is_array($snap) && $snap !== []) {
            return self::deliveryOrderSectionsFromSnapshot($snap);
        }

        return self::deliveryOrderSectionsMinimal($delivery);
    }

    /**
     * @return list<array{title: string, rows: list<array{label: string, value: string}>}>
     */
    private static function deliveryOrderSectionsFromOrder(Order $order): array
    {
        $itemsCount = (int) ($order->items_count ?? $order->items()->count());
        $wholesale = (bool) $order->is_wholesale;
        $itemsLine = $wholesale
            ? $itemsCount.' líneas (cantidades en cajas)'
            : $itemsCount.' líneas (unidades)';

        $status = $order->status instanceof OrderStatus
            ? $order->status->label()
            : (filled($order->status) ? (string) $order->status : '—');

        $scheduled = $order->scheduled_delivery_at !== null
            ? $order->scheduled_delivery_at->timezone(config('app.timezone'))->format('d/m/Y H:i')
            : '—';

        $total = $order->total !== null
            ? number_format((float) $order->total, 2, ',', '.')
            : '—';

        $partner = self::partnerLineFromParts(
            $order->partner_company_code,
            $order->partner_company_id !== null ? (int) $order->partner_company_id : null,
        );

        return [
            [
                'title' => 'Pedido aliado',
                'rows' => [
                    ['label' => 'Nº pedido', 'value' => filled($order->order_number) ? (string) $order->order_number : '—'],
                    ['label' => 'Aliado', 'value' => $partner],
                    ['label' => 'Total', 'value' => $total],
                    ['label' => 'Ítems', 'value' => $itemsLine],
                    ['label' => 'Estado del pedido', 'value' => $status],
                    ['label' => 'Entrega programada', 'value' => $scheduled],
                ],
            ],
            [
                'title' => 'Persona a quien entregar',
                'rows' => [
                    ['label' => 'Nombre', 'value' => filled($order->delivery_recipient_name) ? (string) $order->delivery_recipient_name : '—'],
                    ['label' => 'Teléfono', 'value' => filled($order->delivery_phone) ? (string) $order->delivery_phone : '—'],
                    ['label' => 'Cédula o RIF', 'value' => filled($order->delivery_recipient_document) ? (string) $order->delivery_recipient_document : '—'],
                ],
            ],
            [
                'title' => 'Dirección de entrega',
                'rows' => [
                    ['label' => 'Dirección', 'value' => filled($order->delivery_address) ? (string) $order->delivery_address : '—'],
                    ['label' => 'Ciudad / departamento', 'value' => self::formatCityStateLine($order->delivery_city, $order->delivery_state)],
                    ['label' => 'Notas', 'value' => filled($order->delivery_notes) ? (string) $order->delivery_notes : '—'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return list<array{title: string, rows: list<array{label: string, value: string}>}>
     */
    private static function deliveryOrderSectionsFromSnapshot(array $snap): array
    {
        $code = $snap['partner_company_code'] ?? null;
        $id = isset($snap['partner_company_id']) ? (int) $snap['partner_company_id'] : null;
        $partner = self::partnerLineFromParts(
            is_string($code) || is_numeric($code) ? (string) $code : null,
            $id > 0 ? $id : null,
        );

        $num = $snap['order_number'] ?? null;
        $totalRaw = $snap['total'] ?? null;
        $total = $totalRaw !== null && $totalRaw !== ''
            ? number_format((float) $totalRaw, 2, ',', '.')
            : '—';

        $n = $snap['items_count'] ?? null;
        $wholesale = filter_var($snap['is_wholesale'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $itemsLine = ($n === null || $n === '')
            ? '—'
            : (($wholesale ? (string) $n.' líneas (cajas)' : (string) $n.' líneas (unidades)'));

        $statusRaw = $snap['status'] ?? null;
        $status = is_string($statusRaw) && $statusRaw !== ''
            ? (OrderStatus::tryFrom($statusRaw)?->label() ?? $statusRaw)
            : '—';

        $scheduled = self::formatSnapshotScheduled($snap['scheduled_delivery_at'] ?? null);

        return [
            [
                'title' => 'Pedido aliado',
                'rows' => [
                    ['label' => 'Nº pedido', 'value' => filled($num) ? (string) $num : '—'],
                    ['label' => 'Aliado', 'value' => $partner],
                    ['label' => 'Total', 'value' => $total],
                    ['label' => 'Ítems', 'value' => $itemsLine],
                    ['label' => 'Estado del pedido (snapshot)', 'value' => $status],
                    ['label' => 'Entrega programada', 'value' => $scheduled],
                ],
            ],
            [
                'title' => 'Persona a quien entregar',
                'rows' => [
                    ['label' => 'Nombre', 'value' => self::snapshotStringValue($snap, 'delivery_recipient_name')],
                    ['label' => 'Teléfono', 'value' => self::snapshotStringValue($snap, 'delivery_phone')],
                    ['label' => 'Cédula o RIF', 'value' => self::snapshotStringValue($snap, 'delivery_recipient_document')],
                ],
            ],
            [
                'title' => 'Dirección de entrega',
                'rows' => [
                    ['label' => 'Dirección', 'value' => self::snapshotStringValue($snap, 'delivery_address')],
                    ['label' => 'Ciudad / departamento', 'value' => self::formatCityStateLine($snap['delivery_city'] ?? null, $snap['delivery_state'] ?? null)],
                    ['label' => 'Notas', 'value' => self::snapshotStringValue($snap, 'delivery_notes')],
                ],
            ],
        ];
    }

    /**
     * @return list<array{title: string, rows: list<array{label: string, value: string}>}>
     */
    private static function deliveryOrderSectionsMinimal(Delivery $delivery): array
    {
        $num = filled($delivery->order_number) ? (string) $delivery->order_number : '—';

        return [
            [
                'title' => 'Pedido',
                'rows' => [
                    ['label' => 'Nº pedido', 'value' => $num],
                    ['label' => 'Detalle', 'value' => 'Vincule un pedido o espere la sincronización desde el aliado para ver dirección y destinatario.'],
                ],
            ],
        ];
    }

    private static function partnerLineFromParts(?string $code, ?int $id): string
    {
        if (filled($code) && $id !== null && $id > 0) {
            return $code.' (ID '.$id.')';
        }
        if ($id !== null && $id > 0) {
            return 'ID '.$id;
        }
        if (filled($code)) {
            return (string) $code;
        }

        return '—';
    }

    /**
     * @param  array<string, mixed>  $snap
     */
    private static function snapshotStringValue(array $snap, string $key): string
    {
        $v = $snap[$key] ?? null;

        return filled($v) ? (string) $v : '—';
    }

    private static function formatCityStateLine(mixed $city, mixed $state): string
    {
        $line = trim(implode(', ', array_filter([
            is_scalar($city) || $city instanceof \Stringable ? (string) $city : '',
            is_scalar($state) || $state instanceof \Stringable ? (string) $state : '',
        ])));

        return $line !== '' ? $line : '—';
    }

    private static function formatSnapshotScheduled(mixed $raw): string
    {
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
}
