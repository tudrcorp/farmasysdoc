<?php

namespace App\Filament\Resources\Sales\Pages\Concerns;

use App\Filament\Resources\Sales\Actions\CashRegisterAction;
use Livewire\Attributes\Renderless;

trait InteractsWithPosProductConsult
{
    /**
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     active_ingredient: string,
     *     price_usd: string,
     *     price_ves: string,
     *     quantity: string,
     *     out_of_stock: bool
     * }>
     */
    #[Renderless]
    public function searchPosConsultProducts(string $search = ''): array
    {
        return CashRegisterAction::searchPosConsultProductsForCurrentUser($search, $this);
    }

    public function addPosConsultProduct(int $productId): void
    {
        CashRegisterAction::addConsultProductToMountedRegister($this, $productId);
    }
}
