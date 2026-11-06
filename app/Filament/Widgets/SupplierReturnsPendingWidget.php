<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SupplierReturns\SupplierReturnResource;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\Purchases\SupplierReturnNotifier;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Alerta del dashboard del Administrador: devoluciones de mercancía recibida de más por revisar.
 * Solo aparece cuando hay alguna pendiente.
 */
class SupplierReturnsPendingWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->isAdministrator()
            && SupplierReturn::query()->pendingReview()->exists();
    }

    public function table(Table $table): Table
    {
        $count = SupplierReturn::query()->pendingReview()->count();

        return $table
            ->heading('Devoluciones por revisar ('.$count.')')
            ->description('Mercancía recibida de más registrada por las sucursales. Apruebe o rechace cada una.')
            ->query(fn (): Builder => SupplierReturn::query()
                ->pendingReview()
                ->with(['branch:id,name', 'supplier:id,legal_name,trade_name', 'creator:id,name'])
                ->withCount('items')
                ->withSum('items', 'quantity_excess'))
            ->defaultSort('created_at')
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('number')->label('Número')->badge()->color('warning'),
                TextColumn::make('branch.name')->label('Sucursal'),
                TextColumn::make('supplier.legal_name')
                    ->label('Proveedor')
                    ->formatStateUsing(fn (SupplierReturn $record): string => $record->supplierName()),
                TextColumn::make('items_count')
                    ->label('Productos')
                    ->alignCenter()
                    ->description(fn (SupplierReturn $record): string => SupplierReturnNotifier::quantity((float) $record->items_sum_quantity_excess).' u. de más'),
                TextColumn::make('creator.name')->label('Registró')->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Esperando')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->color(fn (SupplierReturn $record): ?string => $record->created_at?->lt(now()->subDays(2)) ? 'danger' : null),
            ])
            ->recordUrl(fn (SupplierReturn $record): string => SupplierReturnResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('all')
                    ->label('Ver todas')
                    ->icon(Heroicon::ArrowRight)
                    ->color('gray')
                    ->url(SupplierReturnResource::getUrl('index', ['tab' => 'pending'])),
            ]);
    }
}
