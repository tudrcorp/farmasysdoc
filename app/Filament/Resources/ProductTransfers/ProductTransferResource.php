<?php

namespace App\Filament\Resources\ProductTransfers;

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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ProductTransferResource extends Resource
{
    protected static ?string $model = ProductTransfer::class;

    protected static ?string $navigationLabel = 'Traslados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowPath;

    public static function getNavigationGroup(): ?string
    {
        $user = auth()->user();

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
     * Administrador: todos los traslados. Resto: solo los dirigidos a su sucursal (receptora).
     *
     * @return Builder<ProductTransfer>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        if (! $user instanceof User || $user->isAdministrator()) {
            return $query;
        }

        if (! filled($user->branch_id)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('to_branch_id', (int) $user->branch_id);
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof ProductTransfer) {
            return false;
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if (! filled($user->branch_id)) {
            return false;
        }

        return (int) $user->branch_id === (int) $record->to_branch_id;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }

    public static function canEdit(Model $record): bool
    {
        if (! $record instanceof ProductTransfer) {
            return false;
        }

        if ($record->status === 'completed') {
            return false;
        }

        $user = auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }

    /**
     * Acción para sucursal receptora (no administrador): completar traslado con confirmación.
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
            ->modalDescription('Se descontará el stock en origen, se registrará la entrada en destino y se generará la venta interna a costo en la sucursal emisora. Esta operación no se puede deshacer.')
            ->modalSubmitActionLabel('Sí, completar')
            ->successNotificationTitle('Traslado completado')
            ->visible(fn (ProductTransfer $record): bool => self::shouldShowMarkCompletedAction($record))
            ->action(function (ProductTransfer $record, Action $action): void {
                $user = auth()->user();
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

    public static function shouldShowMarkCompletedAction(ProductTransfer $record): bool
    {
        $user = auth()->user();
        if (! $user instanceof User || $user->isAdministrator()) {
            return false;
        }

        $status = strtolower((string) $record->status);
        if (in_array($status, ['completed', 'cancelled'], true)) {
            return false;
        }

        return app(ProductTransferCompletionService::class)->userMayMarkCompleted($user, $record);
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof ProductTransfer) {
            return false;
        }

        if ($record->status === 'completed') {
            return false;
        }

        $user = auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }
}
