<?php

namespace App\Filament\Resources\PettyCashFunds\Concerns;

trait HasPettyCashFundFormPage
{
    /**
     * @return array<int, string>
     */
    public function getPageClasses(): array
    {
        return array_merge(parent::getPageClasses(), [
            'fi-petty-cash-fund-form-page',
            'fi-fixed-positioning-context',
        ]);
    }
}
