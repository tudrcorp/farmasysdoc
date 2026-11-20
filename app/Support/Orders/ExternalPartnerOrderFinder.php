<?php

namespace App\Support\Orders;

use App\Models\Order;
use App\Models\PartnerCompany;

final class ExternalPartnerOrderFinder
{
    public function partner(string $code): ?PartnerCompany
    {
        return PartnerCompany::query()->where('code', $code)->first();
    }

    public function order(PartnerCompany $partner, string $key): ?Order
    {
        $scoped = Order::query()
            ->where(function ($query) use ($partner): void {
                $query->where('partner_company_code', $partner->code)
                    ->orWhere('partner_company_id', $partner->id);
            });

        $record = (clone $scoped)->where('order_number', $key)->first();

        if ($record === null && ctype_digit($key)) {
            $record = (clone $scoped)->whereKey((int) $key)->first();
        }

        return $record;
    }
}
