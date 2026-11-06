<?php

namespace App\Filament\Resources\SupplierReturns\Pages;

use App\Enums\SupplierReturnStatus;
use App\Filament\Resources\SupplierReturns\SupplierReturnResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListSupplierReturns extends ListRecords
{
    protected static string $resource = SupplierReturnResource::class;

    protected static ?string $title = 'Devoluciones a proveedor';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Registrar devolución')->icon(Heroicon::Plus),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tab = fn (string $label, SupplierReturnStatus $status, Heroicon $icon, ?string $badgeColor = null): Tab => Tab::make($label)
            ->icon($icon)
            ->badge(fn (): ?int => $badgeColor !== null ? (SupplierReturnResource::getEloquentQuery()->where('status', $status->value)->count() ?: null) : null)
            ->badgeColor($badgeColor ?? 'gray')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value));

        return [
            'pending' => $tab('Por revisar', SupplierReturnStatus::PendingReview, Heroicon::Clock, 'warning'),
            'approved' => $tab('Por entregar', SupplierReturnStatus::Approved, Heroicon::Truck, 'info'),
            'returned' => $tab('Devueltas', SupplierReturnStatus::Returned, Heroicon::CheckCircle),
            'rejected' => $tab('Rechazadas', SupplierReturnStatus::Rejected, Heroicon::XCircle),
            'all' => Tab::make('Todas'),
        ];
    }
}
