<?php

namespace App\Filament\Resources\PurchaseBooks\Widgets\Concerns;

use App\Models\PurchaseBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Reactive;

trait InteractsWithPurchaseBookListStatsQuery
{
    /**
     * @var array<string, mixed>|null
     */
    #[Reactive]
    public ?array $tableFilters = null;

    /**
     * @return Builder<PurchaseBook>
     */
    protected function scopedPurchaseBookQuery(): Builder
    {
        $query = PurchaseBook::query()
            ->where('tax_period', $this->currentTaxPeriod());

        $filters = $this->tableFilters ?? [];

        $supplierRif = $this->normalizeSelectFilterValue($filters['supplier_rif'] ?? null);
        if ($supplierRif !== null) {
            $query->where('supplier_rif', $supplierRif);
        }

        $dateRange = is_array($filters['invoice_date_between'] ?? null)
            ? $filters['invoice_date_between']
            : [];

        if (filled($dateRange['invoice_from'] ?? null)) {
            $query->whereDate('invoice_date', '>=', (string) $dateRange['invoice_from']);
        }

        if (filled($dateRange['invoice_until'] ?? null)) {
            $query->whereDate('invoice_date', '<=', (string) $dateRange['invoice_until']);
        }

        return $query;
    }

    protected function statsPeriodCode(): string
    {
        return $this->currentTaxPeriod();
    }

    protected function statsPeriodLabel(): string
    {
        $period = $this->statsPeriodCode();

        try {
            return Carbon::createFromFormat('Y/m', $period)->translatedFormat('F Y');
        } catch (\Throwable) {
            return $period;
        }
    }

    protected function statsScopeHint(): string
    {
        $filters = $this->tableFilters ?? [];
        $hasSupplier = filled($this->normalizeSelectFilterValue($filters['supplier_rif'] ?? null));
        $range = is_array($filters['invoice_date_between'] ?? null) ? $filters['invoice_date_between'] : [];
        $hasDates = filled($range['invoice_from'] ?? null) || filled($range['invoice_until'] ?? null);

        if ($hasSupplier || $hasDates) {
            return 'Mes en curso · acotado por filtros';
        }

        return 'Mes en curso';
    }

    protected function currentTaxPeriod(): string
    {
        return now()->format('Y/m');
    }

    private function normalizeSelectFilterValue(mixed $state): ?string
    {
        if (is_array($state) && array_key_exists('value', $state)) {
            $state = $state['value'];
        }

        if (! is_string($state) || $state === '') {
            return null;
        }

        return $state;
    }
}
