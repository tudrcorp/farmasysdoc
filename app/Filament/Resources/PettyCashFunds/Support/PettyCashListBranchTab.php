<?php

namespace App\Filament\Resources\PettyCashFunds\Support;

use App\Models\Branch;
use App\Support\Filament\BranchAuthScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PettyCashListBranchTab
{
    public const string TAB_KEY_PREFIX = 'branch_';

    /**
     * @return Collection<int, Branch>
     */
    public static function branchesForTabs(): Collection
    {
        /** @var Builder<Branch> $query */
        $query = Branch::query()
            ->where('is_active', true)
            ->orderBy('name');

        return BranchAuthScope::applyToBranchFormSelect($query)->get();
    }

    public static function tabKeyForBranch(Branch|int $branch): string
    {
        $id = $branch instanceof Branch ? $branch->getKey() : $branch;

        return self::TAB_KEY_PREFIX.$id;
    }

    public static function branchIdFromTabKey(?string $activeTab): ?int
    {
        if (blank($activeTab) || ! str_starts_with((string) $activeTab, self::TAB_KEY_PREFIX)) {
            return null;
        }

        $id = (int) substr((string) $activeTab, strlen(self::TAB_KEY_PREFIX));

        return $id > 0 ? $id : null;
    }
}
