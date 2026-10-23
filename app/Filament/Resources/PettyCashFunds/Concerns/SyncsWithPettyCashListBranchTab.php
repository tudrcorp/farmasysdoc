<?php

namespace App\Filament\Resources\PettyCashFunds\Concerns;

use App\Filament\Resources\PettyCashFunds\Support\PettyCashListBranchTab;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

trait SyncsWithPettyCashListBranchTab
{
    public ?int $pettyCashListBranchId = null;

    public function mountSyncsWithPettyCashListBranchTab(): void
    {
        $branchId = PettyCashListBranchTab::branchIdFromTabKey(request()->query('tab'));

        if ($branchId === null) {
            $firstBranch = PettyCashListBranchTab::branchesForTabs()->first();
            $branchId = $firstBranch !== null ? (int) $firstBranch->getKey() : null;
        }

        $this->pettyCashListBranchId = $branchId;
    }

    #[On('petty-cash-list-branch-tab-changed')]
    public function onPettyCashListBranchTabChanged(?int $branchId = null): void
    {
        $this->pettyCashListBranchId = $branchId;

        if (isset($this->table) && method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    protected function applyPettyCashListBranchScope(Builder $query, string $branchColumn = 'branch_id'): Builder
    {
        if ($this->pettyCashListBranchId === null) {
            return $query;
        }

        return $query->where($query->qualifyColumn($branchColumn), $this->pettyCashListBranchId);
    }
}
