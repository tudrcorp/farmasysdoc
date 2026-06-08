<?php

namespace App\Support\Filament;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Filtro de sucursal compartido en el dashboard Farmaadmin (persistido en sesión por usuario).
 */
final class DashboardBranchFilter
{
    private const SESSION_PREFIX = 'farmaadmin.dashboard.branch_filter.';

    public static function setSelectedBranchId(?int $branchId): void
    {
        $allowed = self::allowedBranchIdsForCurrentUser();

        if ($branchId !== null && ! in_array($branchId, $allowed, true)) {
            $branchId = null;
        }

        if ($branchId === null) {
            session()->forget(self::sessionKeyForCurrentUser());

            return;
        }

        session([self::sessionKeyForCurrentUser() => $branchId]);
    }

    public static function selectedBranchId(): ?int
    {
        $value = session(self::sessionKeyForCurrentUser());

        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;
        $allowed = self::allowedBranchIdsForCurrentUser();

        if (! in_array($id, $allowed, true)) {
            return null;
        }

        return $id;
    }

    public static function selectedBranchKey(): string
    {
        $id = self::selectedBranchId();

        return $id === null ? 'all' : (string) $id;
    }

    public static function isFilteredToSingleBranch(): bool
    {
        return self::selectedBranchId() !== null;
    }

    public static function selectedBranchLabel(): ?string
    {
        $id = self::selectedBranchId();
        if ($id === null) {
            return null;
        }

        $name = Branch::query()->whereKey($id)->value('name');

        return filled($name) ? Str::limit((string) $name, 40, '…') : ('Sucursal #'.$id);
    }

    public static function shouldShowBranchPicker(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'farmaadmin') {
            return false;
        }

        return count(self::allowedBranchIdsForCurrentUser()) > 1;
    }

    /**
     * @return list<array{id: int, name: string, short_name: string}>
     */
    public static function branchOptionsForPicker(): array
    {
        $ids = self::allowedBranchIdsForCurrentUser();
        if ($ids === []) {
            return [];
        }

        $names = Branch::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id');

        $options = [];
        foreach ($ids as $id) {
            $name = (string) ($names[$id] ?? ('Sucursal #'.$id));
            $options[] = [
                'id' => $id,
                'name' => $name,
                'short_name' => Str::limit($name, 22, '…'),
            ];
        }

        return $options;
    }

    /**
     * IDs de sucursal efectivos para gráficos y stats del dashboard.
     *
     * @return list<int>
     */
    public static function resolvedBranchIdsForCharts(): array
    {
        $allowed = self::allowedBranchIdsForCurrentUser();
        $selected = self::selectedBranchId();

        if ($selected !== null) {
            return [$selected];
        }

        return $allowed;
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public static function applyToSalesQuery(Builder $query): Builder
    {
        BranchAuthScope::applyToSalesQuery($query);

        $selected = self::selectedBranchId();
        if ($selected === null) {
            return $query;
        }

        return $query->where($query->qualifyColumn('branch_id'), $selected);
    }

    /**
     * @return list<int>
     */
    public static function allowedBranchIdsForCurrentUser(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        if ($user->isAdministrator()) {
            return Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        }

        if ($user->hasGerenciaRole()) {
            return array_values(array_unique(array_filter(array_map(
                static fn (mixed $id): int => (int) $id,
                $user->restrictedBranchIdsForQueries(),
            ))));
        }

        if (filled($user->branch_id)) {
            return [(int) $user->branch_id];
        }

        return [];
    }

    private static function sessionKeyForCurrentUser(): string
    {
        $userId = Auth::id();

        return self::SESSION_PREFIX.($userId ?? 'guest');
    }
}
