<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class OrdersTable
{
    /**
     * @param  class-string<\Filament\Resources\Resource>  $urlResource  Recurso cuyas URLs de registro se usan en la tabla (p. ej. panel aliados).
     * @param  bool  $includeBranchAndClientFilters  En el panel aliados suele desactivarse para no listar todos los clientes/sucursales del sistema.
     * @param  bool  $partnerOrderNumberDeliveryModal  Panel aliados: clic en Nº pedido abre datos y foto del repartidor si el pedido está en proceso.
     * @param  bool  $adminDeliveryInsightsColumns  Farmaadmin: minutos solicitud→entrega y calificación del aliado.
     */
    public static function configure(
        Table $table,
        string $urlResource = OrderResource::class,
        bool $includeBranchAndClientFilters = true,
        bool $partnerOrderNumberDeliveryModal = false,
        bool $adminDeliveryInsightsColumns = false,
    ): Table {
        $orderNumberColumn = TextColumn::make('order_number')
            ->label('Nº pedido')
            ->badge()
            ->color('primary')
            ->searchable()
            ->sortable()
            ->weight('medium')
            ->icon(Heroicon::ClipboardDocumentList)
            ->iconColor('gray');

        if ($partnerOrderNumberDeliveryModal) {
            // No usar copyable() aquí: Filament añade click.prevent.stop al badge y bloquea la acción del modal.
            $orderNumberColumn = $orderNumberColumn
                ->tooltip(fn (Order $record): string => match ($record->status) {
                    OrderStatus::InProgress => 'Pulse para ver nombre, correo y foto del repartidor asignado',
                    OrderStatus::Completed => 'Pulse para ver la confirmación de pedido finalizado',
                    default => 'Pulse para ver información del repartidor (disponible cuando el pedido esté en proceso)',
                })
                ->action(self::viewPartnerDeliveryAssigneeModalAction())
                ->extraCellAttributes([
                    'class' => 'fi-ta-cell-partner-order-assignee-modal-trigger',
                ]);
        } else {
            $orderNumberColumn = $orderNumberColumn
                ->copyable()
                ->copyMessage('Número copiado');
        }

        return $table
            ->extraAttributes([
                'class' => 'fi-ta-orders-table',
            ], merge: true)
            ->modifyQueryUsing(function (Builder $query) use ($partnerOrderNumberDeliveryModal): Builder {
                $q = BranchAuthScope::applyToOrdersTableQuery($query)
                    ->with(['client', 'branch', 'partnerCompany'])
                    ->withCount('items');

                if ($partnerOrderNumberDeliveryModal) {
                    $q->with(['partnerDeliveries.user']);
                }

                return $q;
            })
            ->columns([
                $orderNumberColumn,
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->description(fn (Order $record): ?string => self::formatClientDocument($record))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Order $record): string => (string) ($record->client?->name ?? '—'))
                    ->placeholder('—')
                    ->icon(Heroicon::User)
                    ->iconColor('gray'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('partnerCompany.legal_name')
                    ->label('Aliado')
                    ->description(fn (Order $record): ?string => filled($record->partnerCompany?->code)
                        ? 'Código: '.$record->partnerCompany->code
                        : null)
                    ->searchable(query: function (Builder $query, string $search): void {
                        $like = "%{$search}%";
                        $query->whereHas('partnerCompany', function (Builder $q) use ($like): void {
                            $q->where('legal_name', 'like', $like)
                                ->orWhere('trade_name', 'like', $like)
                                ->orWhere('code', 'like', $like)
                                ->orWhere('tax_id', 'like', $like);
                        });
                    })
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::BuildingOffice2)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('partner_pago_movil_reference')
                    ->label('Ref. pago móvil')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('partner_zelle_reference_name')
                    ->label('Nombre Zelle')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('partner_zelle_transaction_number')
                    ->label('Nº trans. Zelle')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('items_count')
                    ->label('Ítems')
                    ->numeric()
                    ->sortable()
                    ->alignment(Alignment::Center)
                    ->icon(Heroicon::Cube)
                    ->iconColor('primary')
                    ->color('primary')
                    ->tooltip('Ver productos y cantidades solicitadas')
                    ->action(self::viewOrderLineItemsTableAction())
                    ->extraCellAttributes([
                        'class' => 'fi-ta-cell-order-items-modal-trigger',
                    ])
                    ->toggleable(),
                TextColumn::make('is_wholesale')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Mayor' : 'Detalle')
                    ->color(fn (?bool $state): string => $state ? 'warning' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?OrderStatus $state): string => $state instanceof OrderStatus ? $state->label() : '—')
                    ->color(fn (?OrderStatus $state): string => $state instanceof OrderStatus ? $state->filamentColor() : 'gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('delivered_at')
                    ->label('Completado el')
                    ->formatStateUsing(function (mixed $state, Order $record): string {
                        if ($record->status !== OrderStatus::Completed) {
                            return '—';
                        }
                        $dt = $record->delivered_at ?? $record->updated_at;

                        return $dt !== null
                            ? $dt->timezone(config('app.timezone'))->format('d/m/Y H:i')
                            : '—';
                    })
                    ->sortable()
                    ->tooltip(fn (Order $record): string => $record->status === OrderStatus::Completed
                        ? 'Fecha de cierre del pedido (entrega registrada o última actualización si no hay fecha de entrega).'
                        : 'Disponible cuando el pedido esté en estado Finalizado.')
                    ->placeholder('—')
                    ->icon(Heroicon::CheckCircle)
                    ->iconColor(fn (Order $record): string => $record->status === OrderStatus::Completed ? 'success' : 'gray'),
                ...self::adminDeliveryInsightColumns($adminDeliveryInsightsColumns),
                TextColumn::make('convenio_type')
                    ->label('Convenio')
                    ->badge()
                    ->formatStateUsing(fn (?ConvenioType $state): string => $state instanceof ConvenioType ? $state->label() : '—')
                    ->color(fn (?ConvenioType $state): string => match ($state) {
                        ConvenioType::Particular => 'gray',
                        ConvenioType::Eps => 'success',
                        ConvenioType::PrivateInsurance => 'info',
                        ConvenioType::PrepaidMedicine => 'info',
                        ConvenioType::Corporate => 'warning',
                        ConvenioType::Other => 'gray',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),
                TextColumn::make('scheduled_delivery_at')
                    ->label('Entrega programada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_summary')
                    ->label('Destino / contacto')
                    ->state(fn (Order $record): string => self::formatDeliverySummary($record))
                    ->searchable(query: function (Builder $query, string $search): void {
                        $like = "%{$search}%";
                        $query->where(function (Builder $q) use ($like): void {
                            $q->where('delivery_recipient_name', 'like', $like)
                                ->orWhere('delivery_phone', 'like', $like)
                                ->orWhere('delivery_address', 'like', $like)
                                ->orWhere('delivery_city', 'like', $like)
                                ->orWhere('delivery_state', 'like', $like);
                        });
                    })
                    ->limit(42)
                    ->tooltip(fn (Order $record): string => self::formatDeliverySummary($record, long: true))
                    ->placeholder('—')
                    ->icon(Heroicon::MapPin)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('convenio_partner_name')
                    ->label('Aseguradora / EPS')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('convenio_reference')
                    ->label('Ref. convenio')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Referencia copiada')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_recipient_name')
                    ->label('Destinatario')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_phone')
                    ->label('Tel. entrega')
                    ->searchable()
                    ->icon(Heroicon::Phone)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_address')
                    ->label('Dirección entrega')
                    ->limit(40)
                    ->tooltip(fn (Order $record): ?string => filled($record->delivery_address) ? $record->delivery_address : null)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_city')
                    ->label('Ciudad entrega')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_state')
                    ->label('Depto. / estado entrega')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dispatched_at')
                    ->label('Despachado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_assignee')
                    ->label('Asignado a')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_total')
                    ->label('Impuestos')
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('discount_total')
                    ->label('Descuentos')
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('No hay pedidos registrados')
            ->emptyStateDescription('Los pedidos aparecerán aquí cuando los crees desde el panel o los integres por API. Usa «Crear» para iniciar uno manualmente.')
            ->emptyStateIcon(Heroicon::ShoppingCart)
            ->recordUrl(fn (Order $record): string => $urlResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                ...array_values(array_filter([
                    SelectFilter::make('status')
                        ->label('Estado')
                        ->options(OrderStatus::options())
                        ->multiple()
                        ->searchable(),
                    SelectFilter::make('convenio_type')
                        ->label('Tipo de convenio')
                        ->options(ConvenioType::options())
                        ->multiple()
                        ->searchable(),
                    $includeBranchAndClientFilters
                        ? SelectFilter::make('branch_id')
                            ->label('Sucursal')
                            ->relationship(
                                name: 'branch',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->multiple()
                        : null,
                    $includeBranchAndClientFilters
                        ? SelectFilter::make('client_id')
                            ->label('Cliente')
                            ->relationship(
                                name: 'client',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->multiple()
                        : null,
                ])),
            ])
            ->recordActions([
                ...($partnerOrderNumberDeliveryModal ? [self::partnerRateDeliveryServiceAction()] : []),
                self::viewPartnerCashPaymentProofTableAction(),
                ViewAction::make()
                    ->label('Ver pedido')
                    ->icon(Heroicon::Eye),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare)
                    ->visible(fn (Order $record) => $urlResource::canEdit($record)),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }

    /**
     * @return list<TextColumn>
     */
    private static function adminDeliveryInsightColumns(bool $enabled): array
    {
        if (! $enabled) {
            return [];
        }

        return [
            TextColumn::make('delivery_fulfillment_duration_minutes')
                ->label('Min. solicitud → entrega')
                ->formatStateUsing(fn (?int $state): string => $state !== null ? number_format($state, 0, ',', '.').' min' : '—')
                ->sortable()
                ->alignment(Alignment::End)
                ->tooltip('Minutos desde la creación del pedido hasta el cierre con evidencia en Entregas.')
                ->icon(Heroicon::Clock)
                ->iconColor('gray'),
            TextColumn::make('partner_delivery_rating')
                ->label('Calif. entrega (aliado)')
                ->formatStateUsing(fn (?int $state): string => $state !== null && $state >= 1 && $state <= 5 ? $state.'/5' : '—')
                ->badge()
                ->color(fn (?int $state): string => match (true) {
                    $state === null => 'gray',
                    $state >= 4 => 'success',
                    $state === 3 => 'warning',
                    default => 'danger',
                })
                ->sortable()
                ->alignment(Alignment::Center)
                ->icon(Heroicon::Star)
                ->iconColor('warning')
                ->tooltip('Estrellas registradas por el aliado tras la entrega.'),
        ];
    }

    /**
     * Panel aliados: calificar el servicio de delivery en pedidos finalizados (visible en Farmaadmin).
     */
    public static function partnerRateDeliveryServiceAction(): Action
    {
        return Action::make('partnerRateDeliveryService')
            ->label('Calificar entrega')
            ->tooltip('Evaluar la experiencia de delivery de este pedido finalizado')
            ->icon(Heroicon::Star)
            ->color('warning')
            ->modalHeading('Calificar servicio de entrega')
            ->modalDescription('Puede actualizar la calificación en cualquier momento. Se mostrará en el panel administrador.')
            ->modalIcon(Heroicon::Star)
            ->modalIconColor('warning')
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel('Guardar calificación')
            ->fillForm(fn (Order $record): array => [
                'rating' => $record->partner_delivery_rating,
            ])
            ->schema([
                Grid::make(1)
                    ->schema([
                        ViewField::make('rating')
                            ->view('filament.forms.partner-delivery-star-rating')
                            ->label('Calificación')
                            ->helperText('Elija de 1 a 5 estrellas.')
                            ->extraFieldWrapperAttributes([
                                'class' => '[&_.fi-fo-field-label-col]:w-full [&_.fi-fo-field-label-col]:text-center [&_.fi-fo-field-label-ctn]:flex [&_.fi-fo-field-label-ctn]:justify-center [&_.fi-fo-field-content-col]:text-center',
                            ])
                            ->columnSpanFull(),
                    ]),
            ])
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Completed
                && filled($record->partner_company_id))
            ->action(function (array $data, Order $record): void {
                $record->refresh();

                if ($record->status !== OrderStatus::Completed) {
                    Notification::make()
                        ->title('El pedido ya no está finalizado')
                        ->warning()
                        ->send();

                    return;
                }

                $ratingRaw = $data['rating'] ?? null;
                if ($ratingRaw === null || $ratingRaw === '') {
                    Notification::make()
                        ->title('Seleccione una calificación')
                        ->body('Pulse de 1 a 5 estrellas antes de guardar.')
                        ->warning()
                        ->send();

                    return;
                }

                if (! is_numeric($ratingRaw)) {
                    Notification::make()
                        ->title('Calificación no válida')
                        ->danger()
                        ->send();

                    return;
                }

                $r = (int) $ratingRaw;
                if ($r < 1 || $r > 5) {
                    Notification::make()
                        ->title('La calificación debe estar entre 1 y 5')
                        ->danger()
                        ->send();

                    return;
                }

                $record->update([
                    'partner_delivery_rating' => $r,
                ]);

                Notification::make()
                    ->title('Calificación guardada')
                    ->success()
                    ->send();
            });
    }

    /**
     * Muestra el comprobante de pago de contado en un slide-over estilo iOS.
     */
    public static function viewPartnerCashPaymentProofTableAction(): Action
    {
        return Action::make('viewPartnerCashPaymentProof')
            ->label('Ver comprobante')
            ->tooltip('Ver el comprobante de pago cargado por el aliado')
            ->icon(Heroicon::DocumentArrowDown)
            ->color('gray')
            ->modalHeading('Comprobante de pago')
            ->modalDescription('Archivo enviado por el aliado al registrar el pedido en pago de contado.')
            ->modalIcon(Heroicon::DocumentArrowDown)
            ->modalIconColor('primary')
            ->modalContent(function (Order $record): View {
                return view('filament.tables.partner-payment-proof-ios-modal', self::buildPartnerPaymentProofModalPayload($record));
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
                'class' => 'fi-ios-payment-proof-modal-window',
            ])
            ->visible(fn (Order $record): bool => filled($record->partner_cash_payment_proof_path));
    }

    /**
     * @return array{url: string, isPdf: bool, fileName: string}
     */
    private static function buildPartnerPaymentProofModalPayload(Order $order): array
    {
        $path = (string) $order->partner_cash_payment_proof_path;
        $url = Storage::disk('public')->url($path);
        $lower = strtolower($path);

        return [
            'url' => $url,
            'isPdf' => str_ends_with($lower, '.pdf'),
            'fileName' => basename($path) !== '' ? basename($path) : 'comprobante',
        ];
    }

    /**
     * Panel aliados: datos del usuario de reparto asignado (tras «Iniciar entrega» en Farmadoc).
     */
    public static function viewPartnerDeliveryAssigneeModalAction(): Action
    {
        return Action::make('viewPartnerOrderDeliveryPerson')
            ->label('Repartidor asignado')
            ->modalHeading(fn (Order $record): string => match ($record->status) {
                OrderStatus::Completed => 'Pedido finalizado',
                OrderStatus::InProgress => 'Persona que realizará la entrega',
                default => 'Información de la entrega',
            })
            ->modalDescription(fn (Order $record): string => match ($record->status) {
                OrderStatus::Completed => 'La entrega de este pedido ya fue completada y registrada.',
                OrderStatus::InProgress => 'Datos de contacto y fotografía registrados por Farmadoc para identificar al repartidor.',
                default => 'Cuando el pedido pase a «En proceso», aquí verá los datos y la foto del repartidor.',
            })
            ->modalIcon(fn (Order $record) => match ($record->status) {
                OrderStatus::Completed => Heroicon::CheckCircle,
                default => Heroicon::UserCircle,
            })
            ->modalIconColor(fn (Order $record): string => match ($record->status) {
                OrderStatus::Completed => 'success',
                default => 'primary',
            })
            ->modalContent(function (Order $record): View {
                $record->loadMissing(['partnerDeliveries.user']);

                return view('filament.tables.partner-order-assignee-ios-modal', [
                    'payload' => self::buildPartnerDeliveryAssigneeModalPayload($record),
                ]);
            })
            ->modalWidth(Width::Medium)
            ->slideOver()
            ->modalSubmitAction(false)
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cerrar')
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]))
            ->extraModalWindowAttributes([
                'class' => 'fi-ios-partner-order-assignee-modal-window',
            ]);
    }

    /**
     * @return array{
     *     isCompleted: bool,
     *     deliveredAtLabel: string|null,
     *     inProgress: bool,
     *     hasLinkedUser: bool,
     *     name: string|null,
     *     email: string|null,
     *     photoUrl: string|null,
     *     assigneeLabel: string|null,
     *     identityDocument: string|null,
     *     mobilePhone: string|null,
     * }
     */
    private static function buildPartnerDeliveryAssigneeModalPayload(Order $order): array
    {
        $isCompleted = $order->status === OrderStatus::Completed;
        $inProgress = $order->status === OrderStatus::InProgress;
        $user = $inProgress ? $order->deliveryAssigneeUser() : null;
        $hasLinkedUser = $user !== null;

        $deliveredAt = $order->delivered_at;
        $deliveredAtLabel = $isCompleted && $deliveredAt !== null
            ? $deliveredAt->timezone(config('app.timezone'))->format('d/m/Y H:i')
            : null;

        return [
            'isCompleted' => $isCompleted,
            'deliveredAtLabel' => $deliveredAtLabel,
            'inProgress' => $inProgress,
            'hasLinkedUser' => $hasLinkedUser,
            'name' => $hasLinkedUser ? (filled(trim((string) $user->name)) ? trim((string) $user->name) : null) : null,
            'email' => $hasLinkedUser && filled($user->email) ? (string) $user->email : null,
            'photoUrl' => $hasLinkedUser ? $user->deliveryPhotoPublicUrl() : null,
            'assigneeLabel' => filled($order->delivery_assignee) ? (string) $order->delivery_assignee : null,
            'identityDocument' => $hasLinkedUser && filled($user->delivery_identity_document)
                ? (string) $user->delivery_identity_document
                : null,
            'mobilePhone' => $hasLinkedUser && filled($user->delivery_mobile_phone)
                ? (string) $user->delivery_mobile_phone
                : null,
        ];
    }

    /**
     * Solo se registra vía la columna «Ítems» (no usar ->hidden(): Filament trata oculto como deshabilitado y no abre el modal).
     */
    public static function viewOrderLineItemsTableAction(): Action
    {
        return Action::make('viewOrderLineItems')
            ->label('Productos del pedido')
            ->modalHeading(fn (Order $record): string => 'Productos del pedido')
            ->modalDescription(fn (Order $record): string => $record->is_wholesale
                ? 'Cantidades solicitadas en cajas (pedido al mayor).'
                : 'Cantidades solicitadas en unidades (al detalle).')
            ->modalIcon(Heroicon::Cube)
            ->modalIconColor('primary')
            ->modalContent(function (Order $record): View {
                return view('filament.tables.order-items-ios-modal', [
                    'rows' => self::buildOrderItemsModalRows($record),
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

    /**
     * @return list<array{name: string, meta: string, quantity: string}>
     */
    private static function buildOrderItemsModalRows(Order $order): array
    {
        $order->loadMissing(['items.product']);

        $wholesale = (bool) $order->is_wholesale;
        $rows = [];

        foreach ($order->items as $item) {
            if (! $item instanceof OrderItem) {
                continue;
            }

            $name = filled($item->product_name_snapshot)
                ? (string) $item->product_name_snapshot
                : (string) ($item->product?->name ?? 'Producto');
            $sku = filled($item->sku_snapshot) ? (string) $item->sku_snapshot : '—';
            $qty = rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',');
            $qtyLabel = $wholesale ? $qty.' cajas' : $qty.' uds.';

            $rows[] = [
                'name' => $name,
                'meta' => 'SKU: '.$sku,
                'quantity' => $qtyLabel,
            ];
        }

        return $rows;
    }

    private static function formatClientDocument(Order $order): ?string
    {
        $client = $order->client;
        if (! $client) {
            return null;
        }

        $doc = trim((string) ($client->document_number ?? ''));
        if ($doc === '') {
            return null;
        }

        $type = trim((string) ($client->document_type ?? ''));

        return $type !== '' ? "{$type} {$doc}" : $doc;
    }

    /**
     * @param  bool  $long  Si es true, añade la dirección completa (p. ej. tooltip).
     */
    private static function formatDeliverySummary(Order $order, bool $long = false): string
    {
        $location = trim(implode(', ', array_filter([$order->delivery_city, $order->delivery_state])));
        $head = array_filter([
            $order->delivery_recipient_name,
            $order->delivery_phone,
            $location !== '' ? $location : null,
        ]);
        $line = $head !== [] ? implode(' · ', $head) : '';

        if ($long && filled($order->delivery_address)) {
            return trim($line !== '' ? $line.' — '.$order->delivery_address : (string) $order->delivery_address);
        }

        if ($line !== '') {
            return $line;
        }

        if (filled($order->delivery_address)) {
            $address = (string) $order->delivery_address;

            return mb_strlen($address) > 48 ? mb_substr($address, 0, 48).'…' : $address;
        }

        return '—';
    }
}
