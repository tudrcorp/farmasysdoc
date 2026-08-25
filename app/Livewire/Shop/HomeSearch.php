<?php

namespace App\Livewire\Shop;

use App\Support\Shop\ShopCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HomeSearch extends Component
{
    public string $term = '';

    public function updatedTerm(string $value): void
    {
        if (mb_strlen($value) > 80) {
            $this->term = mb_substr($value, 0, 80);
        }

        $this->dispatch('home-search-toggle', open: trim($this->term) !== '');
    }

    public function clear(): void
    {
        $this->term = '';
        $this->dispatch('home-search-toggle', open: false);
    }

    public function openFullSearch(): mixed
    {
        $query = trim($this->term);

        $this->dispatch('home-search-toggle', open: false);

        if ($query === '') {
            return $this->redirect(route('shop.search'), navigate: true);
        }

        return $this->redirect(route('shop.search', ['q' => $query]), navigate: true);
    }

    public function render(): View
    {
        $term = trim($this->term);
        $tooShort = $term !== '' && mb_strlen($term) < 2 && ! ctype_digit($term);
        $searching = $term !== '';

        $results = [];

        if ($searching && ! $tooShort) {
            $results = ShopCatalog::search(term: $term, limit: 8);
        }

        return view('livewire.shop.home-search', [
            'results' => $results,
            'tooShort' => $tooShort,
            'searching' => $searching,
        ]);
    }
}
