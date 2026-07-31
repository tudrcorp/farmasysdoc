<?php

namespace App\Filament\Resources\InventoryAudits\Pages;

use App\Enums\InventoryAuditLineStatus;
use App\Filament\Resources\InventoryAudits\InventoryAuditResource;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditLine;
use App\Services\Inventory\InventoryAuditApplyService;
use App\Support\Inventory\InventoryQuantityFormat;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
        $p = $audit->progressCounts();

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
                    ->with(['product:id,name,sku,barcode,cost_price'])
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
                    ->modalDescription('Confirma que la existencia y el costo son correctos. No se aplicarán cambios.')
                    ->visible(fn (InventoryAuditLine $record): bool => $isOpen && $record->isPending())
                    ->action(function (InventoryAuditLine $record): void {
                        try {
                            app(InventoryAuditApplyService::class)->verifyWithoutChanges($record, Auth::user());
                            Notification::make()->title('Producto procesado')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('No se pudo procesar')
                                ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
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
                    ->fillForm(fn (InventoryAuditLine $record): array => [
                        'counted_quantity' => (float) $record->system_quantity,
                        'new_cost_price' => null,
                    ])
                    ->form([
                        TextInput::make('counted_quantity')
                            ->label('Cantidad contada')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(0.001),
                        TextInput::make('new_cost_price')
                            ->label('Nuevo costo (opcional)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->helperText('Déjelo vacío para no modificar el costo. Puede bajar el costo si corresponde.'),
                    ])
                    ->action(function (InventoryAuditLine $record, array $data): void {
                        try {
                            app(InventoryAuditApplyService::class)->applyUpdate($record, [
                                'counted_quantity' => $data['counted_quantity'],
                                'new_cost_price' => $data['new_cost_price'] ?? null,
                            ], Auth::user());
                            Notification::make()->title('Producto actualizado')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('No se pudo actualizar')
                                ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->paginated([25, 50, 100]);
    }
}
