<?php

namespace App\Livewire\Shop;

use App\Livewire\Shop\Concerns\InteractsWithShopCart;
use App\Support\Shop\ShopCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.shop', ['tab' => 'search'])]
#[Title('Buscar')]
class Search extends Component
{
    use InteractsWithShopCart;

    #[Url(as: 'q', except: '')]
    public string $term = '';

    #[Url(as: 'cat', except: null)]
    public ?int $categoryId = null;

    #[Url(as: 'orden', except: 'relevance')]
    public string $sort = 'relevance';

    #[Url(as: 'ofertas', except: false)]
    public bool $onlyOffers = false;

    public int $perPage = 24;

    public function updated(string $property, mixed $value): void
    {
        if (in_array($property, ['term', 'categoryId', 'sort', 'onlyOffers'], true)) {
            $this->perPage = 24;
        }
    }

    public function loadMore(): void
    {
        if ($this->perPage >= 96) {
            return;
        }

        $this->perPage += 24;
    }

    public function toggleCategory(int $categoryId): void
    {
        $this->categoryId = $this->categoryId === $categoryId ? null : $categoryId;
        $this->perPage = 24;
    }

    public function setSort(string $sort): void
    {
        if (! array_key_exists($sort, $this->sortOptions())) {
            return;
        }

        $this->sort = $sort;
        $this->perPage = 24;
    }

    public function clearFilters(): void
    {
        $this->reset(['categoryId', 'sort', 'onlyOffers']);
        $this->perPage = 24;
    }

    public function resetSearch(): void
    {
        $this->term = '';
        $this->perPage = 24;
    }

    /**
     * @return array<string, string>
     */
    public function sortOptions(): array
    {
        return [
            'relevance' => 'Relevancia',
            'price_asc' => 'Menor precio',
            'price_desc' => 'Mayor precio',
            'discount' => 'Mayor descuento',
        ];
    }

    public function hasActiveFilters(): bool
    {
        return $this->categoryId !== null || $this->onlyOffers || $this->sort !== 'relevance';
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function categories(): array
    {
        return ShopCatalog::categories(16);
    }

    public function render(): View
    {
        $term = trim($this->term);
        $tooShort = $term !== '' && mb_strlen($term) < 2 && ! ctype_digit($term);

        $results = [];
        $hasMore = false;

        if (! $tooShort) {
            $results = ShopCatalog::search(
                term: $term,
                categoryId: $this->categoryId,
                sort: $this->sort,
                onlyOffers: $this->onlyOffers,
                limit: $this->perPage + 1,
            );
            $hasMore = count($results) > $this->perPage;
            $results = array_slice($results, 0, $this->perPage);
        }

        return view('livewire.shop.search', [
            'results' => $results,
            'hasMore' => $hasMore,
            'tooShort' => $tooShort,
            'categories' => $this->categories,
            'sortOptions' => $this->sortOptions(),
            'cartQuantities' => $this->cart()->raw(),
        ]);
    }

    protected function shouldRefreshAfterCartChange(): bool
    {
        return false;
    }
}
