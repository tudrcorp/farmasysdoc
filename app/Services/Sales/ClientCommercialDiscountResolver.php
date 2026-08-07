<?php

namespace App\Services\Sales;

use App\Models\Client;
use App\Models\ClientDiscountGroup;

class ClientCommercialDiscountResolver
{
    /**
     * @return array{
     *     percent: float,
     *     source: 'individual'|'group'|'none',
     *     group_id: int|null,
     *     group_name: string|null,
     *     label: string|null,
     * }
     */
    public function resolve(?int $clientId): array
    {
        $empty = [
            'percent' => 0.0,
            'source' => 'none',
            'group_id' => null,
            'group_name' => null,
            'label' => null,
        ];

        if ($clientId === null || $clientId <= 0) {
            return $empty;
        }

        $client = Client::query()
            ->select(['id', 'customer_discount'])
            ->with([
                'discountGroups' => fn ($query) => $query
                    ->select([
                        'client_discount_groups.id',
                        'client_discount_groups.name',
                        'client_discount_groups.discount_percent',
                        'client_discount_groups.is_active',
                    ]),
            ])
            ->find($clientId);

        if (! $client instanceof Client) {
            return $empty;
        }

        $group = $client->discountGroups->first();
        if ($group instanceof ClientDiscountGroup) {
            if (! $group->is_active) {
                return $empty;
            }

            $percent = max(0.0, min(100.0, (float) $group->discount_percent));

            if ($percent <= 0.00001) {
                return $empty;
            }

            return [
                'percent' => $percent,
                'source' => 'group',
                'group_id' => (int) $group->id,
                'group_name' => (string) $group->name,
                'label' => 'Grupo «'.$group->name.'» · '.self::formatPercent($percent).'%',
            ];
        }

        $percent = max(0.0, min(100.0, (float) ($client->customer_discount ?? 0)));
        if ($percent > 0.00001) {
            return [
                'percent' => $percent,
                'source' => 'individual',
                'group_id' => null,
                'group_name' => null,
                'label' => 'Individual · '.self::formatPercent($percent).'%',
            ];
        }

        return $empty;
    }

    public function percentForClientId(?int $clientId): float
    {
        return $this->resolve($clientId)['percent'];
    }

    public function amountFromSubtotal(float $subtotal, float $percent): float
    {
        if ($subtotal <= 0.00001 || $percent <= 0.00001) {
            return 0.0;
        }

        $percent = max(0.0, min(100.0, $percent));

        return min($subtotal, round($subtotal * $percent / 100, 2));
    }

    private static function formatPercent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
    }
}
