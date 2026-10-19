<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\Actions\SupplierDossierAction;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use App\Services\Purchases\SupplierDossierBuilder;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SupplierDossierAction::make(),
            EditAction::make(),
        ];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $model = $this->getRecord();
        if ($model instanceof Supplier) {
            $model->loadMissing('bankAccounts');
        }
    }

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();

        return $record instanceof Supplier ? (string) ($record->legal_name ?: $record->displayName()) : parent::getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $record = $this->getRecord();

        if (! $record instanceof Supplier) {
            return null;
        }

        return new HtmlString(view('filament.suppliers.view-supplier-header', [
            'supplier' => $record,
            'summary' => app(SupplierDossierBuilder::class)->headerSummary($record),
        ])->render());
    }
}
