<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderAcceptance;
use App\Models\User;
use App\Services\Dolar\DolarApiDolaresService;
use App\Services\Orders\OrderAcceptanceNotePdfFactory;
use App\Services\Orders\OrderAcceptancePreview;
use App\Services\Orders\OrderAcceptanceService;
use App\Support\Finance\BcvRate;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\ArrayRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ReviewOrderAcceptance extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Detalle del pedido';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.resources.orders.pages.review-order-acceptance';

    /**
     * @var array<int|string, bool>
     */
    public array $accepted = [];

    /**
     * @var array<int|string, string>
     */
    public array $notes = [];

    public ?float $rate = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $previewCache = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $order = $this->order();

        abort_unless(static::canOpen($order), 403);

        $order->load(['items.product', 'acceptance.items', 'partnerCompany']);

        if ($this->acceptance() !== null || ! $this->statusAllowsDecision()) {
            return;
        }

        $this->rate = BcvRate::truncateOrNull(app(DolarApiDolaresService::class)->getOfficialUsdToVesRate());

        foreach ($this->preview()['rows'] as $row) {
            $id = $row['order_item_id'];
            $this->accepted[$id] = true;
            $this->notes[$id] = '';
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->decisionRecords())
            ->columns([
                CheckboxColumn::make('send')
                    ->label('Enviar')
                    ->state(fn (array $record): bool => $this->isMarked((int) $record['order_item_id']))
                    ->updateStateUsing(function (array $record, mixed $state): bool {
                        $id = (int) $record['order_item_id'];
                        $send = (bool) $state;
                        $accepted = $this->accepted;
                        $accepted[$id] = $send;
                        $accepted[(string) $id] = $send;
                        $this->accepted = $accepted;

                        return $send;
                    }),
                TextColumn::make('name')
                    ->label('Medicamento')
                    ->description(fn (array $record): string => $record['barcode'] !== '' ? (string) $record['barcode'] : 'Sin código de barra')
                    ->wrap()
                    ->weight('medium'),
                TextColumn::make('quantity')
                    ->label('Pedido')
                    ->state(fn (array $record): string => $this->quantity((float) $record['quantity'])),
                TextColumn::make('on_hand')
                    ->label('En Las Delicias')
                    ->state(fn (array $record): string => $this->quantity((float) $record['on_hand']))
                    ->color(fn (array $record): string => ((float) $record['on_hand'] + 0.0001 < (float) $record['quantity']) ? 'danger' : 'gray'),
                TextColumn::make('unit_price_usd')
                    ->label('Precio venta')
                    ->alignment(Alignment::End)
                    ->state(fn (array $record): string => $this->money((float) $record['unit_price_usd'])),
                TextColumn::make('line_total_usd')
                    ->label('Total')
                    ->alignment(Alignment::End)
                    ->state(fn (array $record): string => $this->money((float) $record['line_total_usd']))
                    ->description(fn (array $record): string => $this->bolivars((float) $record['line_total_usd'])),
                TextColumn::make('note')
                    ->label('Observación')
                    ->wrap()
                    ->state(fn (array $record): string => $this->noteFor((int) $record['order_item_id']) ?: '—')
                    ->color(function (array $record): string {
                        $missing = $this->noteFor((int) $record['order_item_id']) === ''
                            && ! $this->isMarked((int) $record['order_item_id']);

                        return $missing ? 'danger' : 'gray';
                    }),
            ])
            ->recordActions([
                Action::make('addRowNote')
                    ->label('Nota')
                    ->icon(Heroicon::ChatBubbleLeftRight)
                    ->modalHeading('Agregar nota')
                    ->modalSubmitActionLabel('Guardar nota')
                    ->fillForm(fn (array $record): array => [
                        'note' => $this->noteFor((int) $record['order_item_id']),
                    ])
                    ->schema([$this->noteTextarea()])
                    ->action(function (array $record, array $data): void {
                        $this->storeNote([(int) $record['order_item_id']], (string) ($data['note'] ?? ''));
                    }),
            ])
            ->recordActionsColumnLabel('Nota')
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('addNote')
                        ->label('Agregar nota')
                        ->icon(Heroicon::ChatBubbleLeftRight)
                        ->modalHeading('Agregar nota')
                        ->modalDescription('La nota se aplica a cada producto seleccionado, sea uno o varios.')
                        ->modalSubmitActionLabel('Guardar nota')
                        ->deselectRecordsAfterCompletion()
                        ->schema([$this->noteTextarea()])
                        ->action(function (Collection $records, array $data): void {
                            $ids = [];

                            foreach ($records as $record) {
                                if (! is_array($record)) {
                                    continue;
                                }

                                $id = (int) ($record['order_item_id'] ?? $record[ArrayRecord::getKeyName()] ?? 0);

                                if ($id !== 0) {
                                    $ids[] = $id;
                                }
                            }

                            if ($ids === []) {
                                foreach ($this->selectedTableRecords as $key) {
                                    $ids[] = (int) $key;
                                }
                            }

                            $this->storeNote($ids, (string) ($data['note'] ?? ''));
                        }),
                ]),
            ])
            ->paginated(false)
            ->emptyStateHeading('Este pedido no tiene productos.')
            ->modelLabel('producto')
            ->pluralModelLabel('productos');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decisionRecords(): array
    {
        $key = ArrayRecord::getKeyName();

        return array_map(function (array $row) use ($key): array {
            $row[$key] = (string) $row['order_item_id'];

            return $row;
        }, $this->preview()['rows']);
    }

    public static function canOpen(Order $order): bool
    {
        $user = Auth::user();

        if (! $user instanceof User || ! OrderResource::canView($order)) {
            return false;
        }

        return ! ($user->isPartnerCompanyUser() && ! $user->isAdministrator());
    }

    public function getHeading(): string|Htmlable
    {
        return 'Detalle del pedido';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $order = $this->order();
        $partner = $order->partnerCompany;
        $parts = array_filter([
            $order->order_number,
            $partner?->trade_name ?: $partner?->legal_name,
            $this->preview()['branch_name'] ? 'Inventario '.$this->preview()['branch_name'] : null,
        ]);

        return implode(' · ', $parts);
    }

    public function order(): Order
    {
        $record = $this->getRecord();

        if (! $record instanceof Order) {
            abort(404);
        }

        return $record;
    }

    public function acceptance(): ?OrderAcceptance
    {
        $acceptance = $this->order()->acceptance;

        return $acceptance instanceof OrderAcceptance ? $acceptance : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        return $this->previewCache ??= app(OrderAcceptancePreview::class)->build($this->order());
    }

    public function blockedMessage(): ?string
    {
        if ($this->acceptance() !== null) {
            return null;
        }

        if (! $this->statusAllowsDecision()) {
            $label = $this->order()->status instanceof OrderStatus ? $this->order()->status->label() : 'cerrado';

            return 'Este pedido está en «'.$label.'» y ya no se puede aceptar desde aquí.';
        }

        if ($this->preview()['rows'] === []) {
            return 'Este pedido no tiene productos.';
        }

        if ($this->rate === null) {
            return 'No se pudo consultar la tasa BCV. Recargue la página en unos minutos.';
        }

        return null;
    }

    public function canDecide(): bool
    {
        return $this->acceptance() === null && $this->blockedMessage() === null;
    }

    public function orderTotalUsd(): float
    {
        return round((float) array_sum(array_column($this->preview()['rows'], 'line_total_usd')), 2);
    }

    public function selectedTotalUsd(): float
    {
        $total = 0.0;

        foreach ($this->preview()['rows'] as $row) {
            if ($this->isMarked((int) $row['order_item_id'])) {
                $total += (float) $row['line_total_usd'];
            }
        }

        return round($total, 2);
    }

    public function money(float $amount): string
    {
        return 'USD '.number_format($amount, 2, ',', '.');
    }

    public function bolivars(float $usd): string
    {
        if ($this->rate === null) {
            return 'Bs. —';
        }

        return 'Bs. '.number_format(round($usd * $this->rate, 2), 2, ',', '.');
    }

    public function quantity(float $amount): string
    {
        $formatted = rtrim(rtrim(number_format($amount, 3, ',', '.'), '0'), ',');
        $unit = $this->order()->is_wholesale ? ' cajas' : ' uds.';

        return $formatted.$unit;
    }

    public function isMarked(int $id): bool
    {
        return (bool) ($this->accepted[$id] ?? $this->accepted[(string) $id] ?? false);
    }

    public function noteFor(int $id): string
    {
        return trim((string) ($this->notes[$id] ?? $this->notes[(string) $id] ?? ''));
    }

    /**
     * La página tiene un pedido, y Filament lo usa como registro por defecto de cualquier acción.
     * En la tabla de líneas eso hace que «Agregar nota» busque el id del pedido entre los productos y se cancele sin abrir el modal.
     */
    public function getDefaultActionRecord(Action $action): ?Model
    {
        if ($action->getTable() !== null) {
            return null;
        }

        return $this->hasRecord() ? $this->order() : null;
    }

    /**
     * @param  list<int>  $ids
     */
    private function storeNote(array $ids, string $note): void
    {
        $note = trim($note);
        $ids = array_values(array_unique(array_filter(
            array_map(intval(...), $ids),
            fn (int $id): bool => $id !== 0,
        )));

        if ($note === '' || $ids === []) {
            Notification::make()
                ->title('No se guardó la nota')
                ->body($ids === [] ? 'Seleccione al menos un producto.' : 'Escriba la nota antes de guardar.')
                ->danger()
                ->send();

            return;
        }

        $notes = $this->notes;

        foreach ($ids as $id) {
            $notes[$id] = $note;
            $notes[(string) $id] = $note;
        }

        $this->notes = $notes;
        $this->flushCachedTableRecords();

        Notification::make()
            ->title('Nota agregada')
            ->body(count($ids) === 1
                ? 'Se guardó en el producto seleccionado.'
                : 'Se guardó en '.count($ids).' productos.')
            ->success()
            ->send();
    }

    private function noteTextarea(): Textarea
    {
        return Textarea::make('note')
            ->label('Nota')
            ->required()
            ->rows(3)
            ->maxLength(1000);
    }

    public function accept(): void
    {
        if (! $this->canDecide()) {
            Notification::make()
                ->title('Este pedido no se puede aceptar')
                ->body($this->blockedMessage() ?? 'Revise el estado del pedido.')
                ->danger()
                ->send();

            return;
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $decisions = [];
        $missingNote = false;

        foreach ($this->preview()['rows'] as $row) {
            $id = (int) $row['order_item_id'];
            $marked = $this->isMarked($id);
            $note = $this->noteFor($id);

            if (! $marked && $note === '') {
                $missingNote = true;
            }

            $decisions[$id] = [
                'accepted' => $marked,
                'rejection_note' => $note,
            ];
        }

        if ($missingNote) {
            Notification::make()
                ->title('Faltan observaciones')
                ->body('Los medicamentos que no se envían necesitan una nota. Use el botón Nota de la fila, o seleccione varios y use Agregar nota.')
                ->danger()
                ->send();

            return;
        }

        try {
            app(OrderAcceptanceService::class)->accept($this->order(), $decisions, $user);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            Notification::make()
                ->title('No se pudo aceptar el pedido')
                ->body(is_string($message) ? $message : 'Revise las líneas e intente de nuevo.')
                ->danger()
                ->send();

            return;
        }

        $this->order()->refresh();
        $this->order()->load(['items.product', 'acceptance.items', 'partnerCompany']);
        $this->previewCache = null;

        Notification::make()
            ->title('Pedido aceptado')
            ->body('El aliado ya puede ver la decisión y descargar la nota de entrega.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al pedido')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->url(fn (): string => OrderResource::getUrl('view', ['record' => $this->order()])),
            Action::make('downloadNote')
                ->label('Descargar nota de entrega')
                ->icon(Heroicon::ArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => $this->acceptance() instanceof OrderAcceptance)
                ->action(function () {
                    $acceptance = $this->acceptance();

                    if (! $acceptance instanceof OrderAcceptance) {
                        return null;
                    }

                    return app(OrderAcceptanceNotePdfFactory::class)->stream($acceptance);
                }),
            Action::make('acceptOrder')
                ->label('Aceptar pedido')
                ->icon(Heroicon::CheckCircle)
                ->color('primary')
                ->visible(fn (): bool => $this->canDecide())
                ->requiresConfirmation()
                ->modalHeading('Aceptar pedido')
                ->modalDescription('Se acepta lo marcado para enviar aunque Las Delicias no tenga la cantidad. Lo que no se envía debe tener una nota, desde el botón Nota de la fila o desde Agregar nota. Esto no genera una venta.')
                ->modalSubmitActionLabel('Aceptar y descontar inventario')
                ->action(fn () => $this->accept()),
        ];
    }

    private function statusAllowsDecision(): bool
    {
        return in_array($this->order()->status, [OrderStatus::Pending, OrderStatus::InProgress], true);
    }
}
