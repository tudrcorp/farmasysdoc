<?php

namespace App\Filament\Resources\ProductTransfers;

use App\Enums\ProductTransferStatus;
use App\Filament\Resources\Concerns\RestrictsAccessForDeliveryUsers;
use App\Filament\Resources\ProductTransfers\Pages\CreateProductTransfer;
use App\Filament\Resources\ProductTransfers\Pages\EditProductTransfer;
use App\Filament\Resources\ProductTransfers\Pages\ListProductTransfers;
use App\Filament\Resources\ProductTransfers\Pages\ViewProductTransfer;
use App\Filament\Resources\ProductTransfers\Schemas\ProductTransferForm;
use App\Filament\Resources\ProductTransfers\Schemas\ProductTransferInfolist;
use App\Filament\Resources\ProductTransfers\Tables\ProductTransfersTable;
use App\Models\ProductTransfer;
use App\Models\User;
use App\Services\Inventory\ProductTransferCompletionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProductTransferResource extends Resource
{
    use RestrictsAccessForDeliveryUsers;

    protected static ?string $model = ProductTransfer::class;

    protected static ?string $navigationLabel = 'Traslados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowPath;

    public static function getNavigationGroup(): ?string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->navigationOperationsGroupLabel() : 'Farmadoc®';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductTransferForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductTransferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductTransfersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductTransfers::route('/'),
            'create' => CreateProductTransfer::route('/create'),
            'view' => ViewProductTransfer::route('/{record}'),
            'edit' => EditProductTransfer::route('/{record}/edit'),
        ];
    }

    /**
     * Administrador: todos. Delivery: todos. Sucursal: traslados donde es origen (envía) o destino (solicita/recibe).
     *
     * @return Builder<ProductTransfer>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return $query;
        }

        if (! filled($user->branch_id)) {
            return $query->whereRaw('1 = 0');
        }

        $branchId = (int) $user->branch_id;

        return $query->where(function (Builder $q) use ($branchId): void {
            $q->where('to_branch_id', $branchId)
                ->orWhere('from_branch_id', $branchId);
        });
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof ProductTransfer) {
            return false;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return true;
        }

        if (! filled($user->branch_id)) {
            return false;
        }

        $branchId = (int) $user->branch_id;

        return $branchId === (int) $record->to_branch_id
            || $branchId === (int) $record->from_branch_id;
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isDeliveryUser()) {
            return false;
        }

        return $user->isAdministrator() || filled($user->branch_id);
    }

    public static function canEdit(Model $record): bool
    {
        if (! $record instanceof ProductTransfer) {
            return false;
        }

        if ($record->status === ProductTransferStatus::Completed) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && $user->isAdministrator();
    }

    /**
     * Delivery toma un traslado pendiente: estado En proceso y registro del usuario.
     */
    public static function takeTransferAction(): Action
    {
        return Action::make('takeTransfer')
            ->label('Tomar traslado')
            ->tooltip('Indica que usted realizará el envío; el traslado pasa a «En proceso».')
            ->icon(Heroicon::PlayCircle)
            ->color('warning')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::PlayCircle)
            ->modalIconColor('warning')
            ->modalHeading(fn (ProductTransfer $record): string => '¿Tomar el traslado '.$record->code.'?')
            ->modalDescription('Quedará registrado como responsable de entrega. La sucursal solicitante podrá marcar «Completado» al recibir la mercancía.')
            ->modalSubmitActionLabel('Sí, tomar traslado')
            ->successNotificationTitle('Traslado en proceso')
            ->visible(fn (ProductTransfer $record): bool => self::shouldShowTakeTransferAction($record))
            ->action(function (ProductTransfer $record, Action $action): void {
                $user = Auth::user();
                if (! $user instanceof User || ! $user->isDeliveryUser()) {
                    return;
                }

                if ($record->status !== ProductTransferStatus::Pending) {
                    Notification::make()
                        ->warning()
                        ->title('Estado no válido')
                        ->body('Solo los traslados pendientes pueden tomarse.')
                        ->send();
                    $action->halt();

                    return;
                }

                $actor = filled($user->email) ? (string) $user->email : (string) ($user->name ?? 'usuario');

                $record->forceFill([
                    'status' => ProductTransferStatus::InProgress,
                    'delivery_user_id' => $user->id,
                    'in_progress_at' => now(),
                    'updated_by' => $actor,
                ])->save();
            });
    }

    public static function shouldShowTakeTransferAction(ProductTransfer $record): bool
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isDeliveryUser()) {
            return false;
        }

        return $record->status === ProductTransferStatus::Pending;
    }

    /**
     * Gerencia o administrador: completar inventario y venta solo si está En proceso.
     */
    public static function markCompletedAction(): Action
    {
        return Action::make('markCompleted')
            ->label('Marcar completado')
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::CheckCircle)
            ->modalIconColor('success')
            ->modalHeading(fn (ProductTransfer $record): string => '¿Completar el traslado '.$record->code.'?')
            ->modalDescription('Confirme que la mercancía llegó a su sucursal. Se descontará el stock en origen, se registrará la entrada en destino y se generará la venta interna a costo en la sucursal emisora. Esta operación no se puede deshacer.')
            ->modalSubmitActionLabel('Sí, completar')
            ->successNotificationTitle('Traslado completado')
            ->visible(fn (ProductTransfer $record): bool => self::shouldShowMarkCompletedAction($record))
            ->action(function (ProductTransfer $record, Action $action): void {
                $user = Auth::user();
                if (! $user instanceof User) {
                    return;
                }

                try {
                    app(ProductTransferCompletionService::class)->complete(
                        $record->fresh([
                            'items' => fn ($q) => $q->orderBy('id'),
                            'items.product',
                        ]),
                        $user,
                    );
                } catch (ValidationException $exception) {
                    $message = collect($exception->errors())->flatten()->first()
                        ?? 'No se pudo completar el traslado.';

                    Notification::make()
                        ->danger()
                        ->title('No se pudo completar')
                        ->body($message)
                        ->persistent()
                        ->send();

                    $action->halt();
                }
            });
    }

    /**
     * Gerencia y Administrador: cambio manual de estado con selector.
     */
    public static function adminChangeStatusAction(): Action
    {
        return Action::make('adminChangeStatus')
            ->label('Cambiar estatus')
            ->icon(Heroicon::ArrowsRightLeft)
            ->color('gray')
            ->visible(fn (): bool => Auth::user() instanceof User && (Auth::user()->isAdministrator() || Auth::user()->isManager()))
            ->form([
                Radio::make('status')
                    ->label('Nuevo estatus')
                    ->options(fn (ProductTransfer $record): array => self::adminStatusOptionsForRecord($record))
                    ->inline(false)
                    ->required(),
            ])
            ->fillForm(function (ProductTransfer $record): array {
                $current = $record->status;

                return [
                    'status' => $current instanceof ProductTransferStatus
                        ? $current->value
                        : (string) $current,
                ];
            })
            ->requiresConfirmation()
            ->modalHeading(fn (ProductTransfer $record): string => 'Cambiar estatus de '.$record->code)
            ->modalDescription('Seleccione el nuevo estado del traslado. Si marca «Completado», el sistema aplicará movimientos de inventario y venta interna.')
            ->modalSubmitActionLabel('Guardar estatus')
            ->extraModalWindowAttributes([
                'class' => 'fi-ios-transfer-status-modal-window',
            ])
            ->action(function (array $data, ProductTransfer $record, Action $action): void {
                $user = Auth::user();
                if (! $user instanceof User || (! $user->isAdministrator() && ! $user->isManager())) {
                    return;
                }

                $newStatus = ProductTransferStatus::tryFrom((string) ($data['status'] ?? ''));
                if (! $newStatus instanceof ProductTransferStatus) {
                    Notification::make()
                        ->warning()
                        ->title('Estado no válido')
                        ->send();
                    $action->halt();

                    return;
                }

                $record = $record->fresh();
                if (! $record instanceof ProductTransfer) {
                    $action->halt();

                    return;
                }

                $currentStatus = $record->status instanceof ProductTransferStatus
                    ? $record->status
                    : ProductTransferStatus::tryFrom((string) $record->status);

                if ($currentStatus instanceof ProductTransferStatus && $currentStatus === $newStatus) {
                    Notification::make()
                        ->title('El traslado ya tiene ese estatus.')
                        ->info()
                        ->send();

                    return;
                }

                if ($currentStatus === ProductTransferStatus::Completed || $record->sale_id !== null) {
                    Notification::make()
                        ->warning()
                        ->title('No se puede cambiar')
                        ->body('El traslado ya fue completado y tiene movimientos aplicados.')
                        ->send();
                    $action->halt();

                    return;
                }

                if ($newStatus === ProductTransferStatus::Completed) {
                    try {
                        app(ProductTransferCompletionService::class)->complete(
                            $record->fresh([
                                'items' => fn ($query) => $query->orderBy('id'),
                                'items.product',
                            ]),
                            $user,
                        );
                    } catch (ValidationException $exception) {
                        $message = collect($exception->errors())->flatten()->first()
                            ?? 'No se pudo completar el traslado.';

                        Notification::make()
                            ->danger()
                            ->title('No se pudo cambiar el estatus')
                            ->body($message)
                            ->persistent()
                            ->send();
                        $action->halt();
                    }

                    return;
                }

                $actor = filled($user->email)
                    ? (string) $user->email
                    : (string) ($user->name ?? 'usuario');

                $payload = [
                    'status' => $newStatus,
                    'updated_by' => $actor,
                ];

                if ($newStatus === ProductTransferStatus::Pending) {
                    $payload['in_progress_at'] = null;
                    $payload['delivery_user_id'] = null;
                }

                if ($newStatus === ProductTransferStatus::InProgress && ! filled($record->in_progress_at)) {
                    $payload['in_progress_at'] = now();
                }

                $record->forceFill($payload)->save();

                Notification::make()
                    ->success()
                    ->title('Estatus actualizado')
                    ->body('Nuevo estado: '.$newStatus->label())
                    ->send();
            });
    }

    public static function shouldShowMarkCompletedAction(ProductTransfer $record): bool
    {
        if ($record->sale_id !== null) {
            return false;
        }

        if (in_array($record->status, [ProductTransferStatus::Completed, ProductTransferStatus::Cancelled], true)) {
            return false;
        }

        if ($record->status !== ProductTransferStatus::InProgress) {
            return false;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        return app(ProductTransferCompletionService::class)->userMayMarkCompleted($user, $record);
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof ProductTransfer) {
            return false;
        }

        if ($record->status === ProductTransferStatus::Completed) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && $user->isAdministrator();
    }

    /**
     * @return array<string, string>
     */
    private static function adminStatusOptionsForRecord(ProductTransfer $record): array
    {
        if ($record->sale_id !== null || $record->status === ProductTransferStatus::Completed) {
            return [
                ProductTransferStatus::Completed->value => ProductTransferStatus::Completed->label(),
            ];
        }

        return [
            ProductTransferStatus::Pending->value => ProductTransferStatus::Pending->label(),
            ProductTransferStatus::InProgress->value => ProductTransferStatus::InProgress->label(),
            ProductTransferStatus::Completed->value => ProductTransferStatus::Completed->label(),
            ProductTransferStatus::Cancelled->value => ProductTransferStatus::Cancelled->label(),
        ];
    }
}
