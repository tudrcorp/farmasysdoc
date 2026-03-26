<?php

namespace App\Services\Marketing;

use App\Enums\SaleStatus;
use App\Models\Client;
use App\Models\MarketingSegment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;

class MarketingSegmentClientResolver
{
    public function __construct(
        protected MarketingAnalyticsService $analytics,
    ) {}

    /**
     * @return Builder<Client>
     */
    public function queryForSegment(MarketingSegment $segment): Builder
    {
        $query = Client::query();
        $rules = is_array($segment->rules) ? $segment->rules : [];

        if (($rules['active_only'] ?? false) === true) {
            $query->where('status', 'active');
        }

        if (($rules['has_email'] ?? false) === true) {
            $query->whereNotNull('email')->where('email', '!=', '');
        }

        if (($rules['has_phone'] ?? false) === true) {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        }

        $minPurchases = isset($rules['min_purchases']) ? (int) $rules['min_purchases'] : null;
        if ($minPurchases !== null && $minPurchases > 0) {
            $sub = Sale::query()
                ->selectRaw('client_id')
                ->where('status', SaleStatus::Completed)
                ->whereNotNull('client_id')
                ->groupBy('client_id')
                ->havingRaw('COUNT(*) >= ?', [$minPurchases]);

            $query->whereIn('id', $sub);
        }

        $minLifetime = isset($rules['min_lifetime_value']) ? (float) $rules['min_lifetime_value'] : null;
        if ($minLifetime !== null && $minLifetime > 0) {
            $sub = $this->analytics->completedSalesQuery()
                ->selectRaw('client_id')
                ->whereNotNull('client_id')
                ->groupBy('client_id')
                ->havingRaw('SUM(CAST(total AS DECIMAL(14,2))) >= ?', [$minLifetime]);

            $query->whereIn('id', $sub);
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    public function clientIdsForSegment(MarketingSegment $segment): array
    {
        return $this->queryForSegment($segment)->pluck('id')->all();
    }
}
