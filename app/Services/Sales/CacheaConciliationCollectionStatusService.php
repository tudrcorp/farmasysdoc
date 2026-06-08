<?php

namespace App\Services\Sales;

use App\Enums\ConciliationCacheaCollectionStatus;
use App\Models\ConciliationCachea;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

final class CacheaConciliationCollectionStatusService
{
    /**
     * @param  Collection<int, ConciliationCachea>  $records
     */
    public static function markAsAmountReceived(Collection $records, ?User $user = null): int
    {
        $user ??= Auth::user();
        $actor = $user instanceof User
            ? ($user->email ?? $user->name ?? 'sistema')
            : 'sistema';

        $ids = $records
            ->filter(fn (ConciliationCachea $record): bool => $record->collection_status === ConciliationCacheaCollectionStatus::PendingCollection)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        return ConciliationCachea::query()
            ->whereIn('id', $ids)
            ->update([
                'collection_status' => ConciliationCacheaCollectionStatus::AmountReceived,
                'collection_status_at' => now(),
                'collection_status_by' => $actor,
            ]);
    }
}
