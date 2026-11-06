<?php

namespace App\Filament\Resources\SupplierReturns\Pages;

use App\Filament\Resources\SupplierReturns\Actions\SupplierReturnActions;
use App\Filament\Resources\SupplierReturns\SupplierReturnResource;
use App\Models\SupplierReturn;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewSupplierReturn extends ViewRecord
{
    protected static string $resource = SupplierReturnResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing(['branch', 'supplier', 'purchase', 'items', 'attachments', 'events.user', 'creator', 'reviewer', 'returner']);
    }

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();

        return $record instanceof SupplierReturn ? 'Devolución '.$record->number : parent::getTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            ...SupplierReturnActions::all(),
            EditAction::make()->visible(fn (SupplierReturn $record): bool => SupplierReturnResource::canEdit($record)),
        ];
    }
}
