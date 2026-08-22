<?php

namespace App\Livewire\Shop;

use App\Support\Shop\ShopCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.shop', ['tab' => 'categories'])]
#[Title('Categorías')]
class Categories extends Component
{
    public function render(): View
    {
        return view('livewire.shop.categories', [
            'categories' => ShopCatalog::categories(),
        ]);
    }
}
