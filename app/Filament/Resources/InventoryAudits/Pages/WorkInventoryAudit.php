<?php

namespace App\Filament\Resources\InventoryAudits\Pages;

use App\Enums\InventoryAuditLineStatus;
use App\Filament\Resources\InventoryAudits\InventoryAuditResource;
use App\Filament\Resources\InventoryAudits\Support\InventoryAuditApplyUpdateForm;
use App\Models\Inventory;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditLine;
use App\Services\Inventory\InventoryAuditApplyService;
use App\Services\Inventory\InventoryAuditOpenLineSyncService;
use App\Support\Inventory\InventoryAuditTrace;
use App\Support\Inventory\InventoryQuantityFormat;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

class WorkInventoryAudit extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = InventoryAuditResource::class;

    protected static ?string $title = 'Trabajar auditoría';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.resources.inventory-audits.pages.work-inventory-audit';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(
            InventoryAuditResource::canView($this->getRecord()),
            403,
        );

        $audit = $this->getRecord();
        if ($audit instanceof InventoryAudit && $audit->isOpen()) {
            $started = microtime(true);
            $updated = app(InventoryAuditOpenLineSyncService::class)->refreshPendingLinesForAudit($audit);
            InventoryAuditTrace::info('page.mount_sync', [
                'audit_id' => (int) $audit->getKey(),
                'updated_lines' => $updated,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        }
    }

    public function hydrate(): void
    {
        try {
            $record = $this->getRecord();

            InventoryAuditTrace::info('page.hydrate', [
                'audit_id' => $record instanceof InventoryAudit ? (int) $record->getKey() : null,
                'mounted_actions' => collect($this->mountedActions ?? [])->pluck('name')->all(),
                'content_kb' => round(strlen((string) request()->getContent()) / 1024, 1),
            ]);
        } catch (Throwable $e) {
            InventoryAuditTrace::error('page.hydrate.fail', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function mountAction(string $name, array $arguments = [], array $context = []): mixed
    {
        $started = microtime(true);
        InventoryAuditTrace::info('mountAction.start', [
            'name' => $name,
            'record_key' => $context['recordKey'] ?? ($arguments['recordKey'] ?? null),
            'table' => (bool) ($context['table'] ?? false),
        ]);

        try {
            $result = parent::mountAction($name, $arguments, $context);
            InventoryAuditTrace::info('mountAction.end', [
                'name' => $name,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'opened_modal' => $this->mountedActions !== [],
            ]);

            return $result;
        } catch (Throwable $e) {
            InventoryAuditTrace::error('mountAction.fail', [
                'name' => $name,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function callMountedAction(array $arguments = []): mixed
    {
        $started = microtime(true);
        $name = data_get($this->mountedActions, array_key_last($this->mountedActions ?? []).'.name');
        InventoryAuditTrace::info('callMountedAction.start', [
            'name' => $name,
            'arguments_keys' => array_keys($arguments),
        ]);

        try {
            $result = parent::callMountedAction($arguments);
            InventoryAuditTrace::info('callMountedAction.end', [
                'name' => $name,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ]);

            return $result;
        } catch (Throwable $e) {
            InventoryAuditTrace::error('callMountedAction.fail', [
                'name' => $name,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getHeading(): string|Htmlable
    {
        /** @var InventoryAudit $audit */
        $audit = $this->getRecord();

        return 'Auditoría #'.$audit->getKey().' · '.($audit->branch?->name ?? 'Sucursal');
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var InventoryAudit $audit */
        $audit = $this->getRecord();
        $p = once(fn (): array => $audit->progressCounts());

        return 'Progreso: '.$p['processed'].'/'.$p['total']
            .' · Pendientes: '.$p['pending']
            .' · Sin cambios: '.$p['verified']
            .' · Actualizados: '.$p['updated']
            .($audit->isOpen() ? '' : ' · Sesión cerrada (solo lectura)');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewDetail')
                ->label('Ver detalle')
                ->icon(Heroicon::Eye)
                ->color('gray')
                ->url(fn (): string => InventoryAuditResource::getUrl('view', ['record' => $this->getRecord()])),
            Action::make('close')
                ->label('Cerrar auditoría')
                ->icon(Heroicon::LockClosed)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord() instanceof InventoryAudit && $this->getRecord()->isOpen())
                ->action(function (): void {
                    $record = $this->getRecord();
                    if (! $record instanceof InventoryAudit) {
                        return;
                    }

                    try {
                        app(InventoryAuditApplyService::class)->close($record, Auth::user());
                        Notification::make()->title('Auditoría cerrada')->success()->send();
                        $this->redirect(InventoryAuditResource::getUrl('view', ['record' => $record]));
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('No se pudo cerrar')
                            ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        /** @var InventoryAudit $audit */
        $audit = $this->getRecord();
        $isOpen = $audit->isOpen();

        return $table
            ->query(
                InventoryAuditLine::query()
                    ->where('inventory_audit_id', $audit->getKey())
                    ->with([
                        'product:id,name,sku,barcode,cost_price,product_category_id,applies_vat',
                        'inventory:id,quantity',
                    ])
            )
            ->defaultSort('id')
            ->columns([
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('product.barcode')
                    ->label('Código')
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('system_quantity')
                    ->label('Existencia sistema')
                    ->state(function (InventoryAuditLine $record): mixed {
                        if ($record->isPending()) {
                            return $record->inventory?->quantity ?? $record->system_quantity;
                        }

                        return $record->system_quantity;
                    })
                    ->formatStateUsing(fn ($state): string => InventoryQuantityFormat::display($state)),
                TextColumn::make('system_cost_price')
                    ->label('Costo')
                    ->money('USD'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (InventoryAuditLineStatus|string|null $state): string => $state instanceof InventoryAuditLineStatus
                        ? $state->label()
                        : (InventoryAuditLineStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (InventoryAuditLineStatus|string|null $state): string => $state instanceof InventoryAuditLineStatus
                        ? $state->filamentColor()
                        : (InventoryAuditLineStatus::tryFrom((string) $state)?->filamentColor() ?? 'gray')),
                TextColumn::make('counted_quantity')
                    ->label('Contado')
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : InventoryQuantityFormat::display($state))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(InventoryAuditLineStatus::options())
                    ->default(InventoryAuditLineStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Sin modificaciones')
                    ->icon(Heroicon::CheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar sin modificaciones')
                    ->modalDescription('Confirma que la existencia y el costo actuales son correctos. No se aplicarán cambios de cantidad.')
                    ->modalSubmitActionLabel('Confirmar')
                    ->visible(fn (InventoryAuditLine $record): bool => $isOpen && $record->isPending())
                    ->action(function (InventoryAuditLine $record): void {
                        $started = microtime(true);
                        InventoryAuditTrace::info('action.verify.begin', [
                            'line_id' => (int) $record->getKey(),
                            'product_id' => (int) $record->product_id,
                            'status' => $record->status instanceof InventoryAuditLineStatus
                                ? $record->status->value
                                : (string) $record->status,
                        ]);

                        try {
                            $previousSystem = round((float) $record->system_quantity, 3);
                            $processed = app(InventoryAuditApplyService::class)
                                ->verifyWithoutChanges($record, Auth::user());
                            $current = round((float) $processed->counted_quantity, 3);

                            InventoryAuditTrace::info('action.verify.done', [
                                'line_id' => (int) $record->getKey(),
                                'ms' => (int) round((microtime(true) - $started) * 1000),
                                'new_status' => $processed->status instanceof InventoryAuditLineStatus
                                    ? $processed->status->value
                                    : (string) $processed->status,
                            ]);

                            $notification = Notification::make()
                                ->title('Producto procesado')
                                ->success();

                            if (abs($current - $previousSystem) > 0.0001) {
                                $notification->body(
                                    'La existencia de sistema cambió durante la auditoría (ahora: '
                                    .InventoryQuantityFormat::display($current)
                                    .'). Se confirmó el stock actual sin modificarlo.'
                                );
                            }

                            $notification->send();
                        } catch (ValidationException $e) {
                            $message = collect($e->errors())->flatten()->first() ?: 'Error de validación.';
                            InventoryAuditTrace::error('action.verify.validation', [
                                'line_id' => (int) $record->getKey(),
                                'ms' => (int) round((microtime(true) - $started) * 1000),
                                'message' => $message,
                            ]);
                            Notification::make()
                                ->title('No se pudo procesar')
                                ->body($message)
                                ->danger()
                                ->send();
                        } catch (Throwable $e) {
                            report($e);
                            InventoryAuditTrace::error('action.verify.exception', [
                                'line_id' => (int) $record->getKey(),
                                'ms' => (int) round((microtime(true) - $started) * 1000),
                                'exception' => $e::class,
                                'message' => $e->getMessage(),
                            ]);
                            Notification::make()
                                ->title('No se pudo procesar')
                                ->body($e->getMessage() !== '' ? $e->getMessage() : $e::class)
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('applyUpdate')
                    ->label('Aplicar actualización')
                    ->icon(Heroicon::PencilSquare)
                    ->color('warning')
                    ->visible(fn (InventoryAuditLine $record): bool => $isOpen && $record->isPending())
                    ->modalHeading('Aplicar actualización')
                    ->modalWidth(Width::Large)
                    ->fillForm(function (InventoryAuditLine $record): array {
                        $record->loadMissing(['product', 'inventory']);

                        if ($record->inventory instanceof Inventory) {
                            app(InventoryAuditOpenLineSyncService::class)
                                ->syncPendingLineForInventory($record->inventory);
                            $record->refresh();
                            $record->loadMissing(['product', 'inventory']);
                        }

                        $liveQuantity = $this->liveSystemQuantity($record);

                        if ($record->product === null) {
                            return [
                                'counted_quantity' => $liveQuantity,
                                'new_cost_price' => null,
                            ];
                        }

                        return InventoryAuditApplyUpdateForm::fillFromProductAndBranch(
                            product: $record->product,
                            branchId: (int) $record->branch_id,
                            systemQuantity: $liveQuantity,
                            systemCostPrice: (float) $record->system_cost_price,
                        );
                    })
                    ->form(InventoryAuditApplyUpdateForm::fields())
                    ->tap(InventoryAuditApplyUpdateForm::configureOtpGatedModalSubmit(...))
                    ->successNotificationTitle('Cambio aplicado con éxito')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Cambio aplicado con éxito')
                            ->body('El producto fue actualizado correctamente.')
                    )
                    ->action(function (InventoryAuditLine $record, array $data, Action $action, $livewire): void {
                        try {
                            app(InventoryAuditApplyService::class)->applyUpdate($record, [
                                'counted_quantity' => $data['counted_quantity'],
                                'new_cost_price' => $data['new_cost_price'] ?? null,
                                'product_category_id' => $data['product_category_id'] ?? null,
                                'otp_code' => isset($data['otp_code']) ? (string) $data['otp_code'] : null,
                            ], Auth::user());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('No se pudo actualizar')
                                ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                                ->danger()
                                ->send();

                            $action->halt();

                            return;
                        }

                        if (is_object($livewire) && method_exists($livewire, 'unmountAction')) {
                            $livewire->unmountAction();
                        }
                    }),
            ])
            ->paginated([25, 50, 100]);
    }

    private function liveSystemQuantity(InventoryAuditLine $record): float
    {
        $live = $record->inventory?->quantity
            ?? Inventory::query()->whereKey($record->inventory_id)->value('quantity');

        return round((float) ($live ?? $record->system_quantity), 3);
    }
}
