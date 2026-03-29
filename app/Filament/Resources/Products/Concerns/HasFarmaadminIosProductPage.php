<?php

namespace App\Filament\Resources\Products\Concerns;

trait HasFarmaadminIosProductPage
{
    /**
     * @return array<int, string>
     */
    public function getPageClasses(): array
    {
        return array_merge(parent::getPageClasses(), [
            'fi-farmaadmin-ios-product-page',
        ]);
    }
}
