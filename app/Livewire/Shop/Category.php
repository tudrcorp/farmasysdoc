<?php

namespace App\Livewire\Shop;

use App\Livewire\Shop\Concerns\InteractsWithShopCart;
use App\Models\ProductCategory;
use App\Support\Shop\ShopCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.shop', ['tab' => 'categories'])]
class Category extends Component
{
    use InteractsWithShopCart;

    public string $slug;

    #[Url(as: 'orden', except: 'relevance')]
    public string $sort = 'relevance';

    #[Url(as: 'ofertas', except: false)]
    public bool $onlyOffers = false;

    public int $perPage = 24;

    public function mount(string $category): void
    {
        $this->slug = $category;

        abort_unless($this->category() instanceof ProductCategory, 404);
    }

    public function category(): ?ProductCategory
    {
        return ShopCatalog::findCategory($this->slug);
    }

    public function loadMore(): void
    {
        if ($this->perPage >= 96) {
            return;
        }

        $this->perPage += 24;
    }

    public function setSort(string $sort): void
    {
        if (! in_array($sort, ['relevance', 'price_asc', 'price_desc', 'discount'], true)) {
            return;
        }

        $this->sort = $sort;
        $this->perPage = 24;
    }

    public function updated(string $property, mixed $value): void
    {
        if (in_array($property, ['sort', 'onlyOffers'], true)) {
            $this->perPage = 24;
        }
    }

    public function render(): View
    {
        $category = $this->category();
        abort_unless($category instanceof ProductCategory, 404);

        $results = ShopCatalog::search(
            categoryId: (int) $category->id,
            sort: $this->sort,
            onlyOffers: $this->onlyOffers,
            limit: $this->perPage + 1,
        );

        return view('livewire.shop.category', [
            'category' => $category,
            'results' => array_slice($results, 0, $this->perPage),
            'hasMore' => count($results) > $this->perPage,
            'sortOptions' => [
                'relevance' => 'Destacados',
                'price_asc' => 'Menor precio',
                'price_desc' => 'Mayor precio',
                'discount' => 'Mayor descuento',
            ],
            'cartQuantities' => $this->cart()->raw(),
        ]);
    }

    protected function shouldRefreshAfterCartChange(): bool
    {
        return false;
    }
}
