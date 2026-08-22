<?php

namespace App\Support\Shop;

use App\Enums\SaleStatus;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleItem;
use App\Support\Storefront\StorefrontProductPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Consultas de catálogo para la PWA `/app`.
 *
 * Toda consulta pública sale de {@see self::inStockQuery()}: solo productos activos
 * con existencia disponible real (cantidad menos reservado) en alguna sucursal.
 *
 * Las lecturas de portada y búsqueda se cachean con TTL corto y un número de
 * versión para invalidar en bloque cuando cambia un producto.
 */
final class ShopCatalog
{
    private const VERSION_KEY = 'shop.catalog.version';

    private const TTL_SEARCH = 20;

    private const TTL_HOME = 45;

    private const TTL_CATEGORIES = 300;

    private const TTL_BESTSELLERS = 600;

    /**
     * Invalida portada, categorías y búsquedas cacheadas.
     */
    public static function bump(): void
    {
        Cache::increment(self::VERSION_KEY);
    }

    /**
     * Productos del carrito con su existencia disponible, indexados por id.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, Product>
     */
    public static function productsForCart(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return self::inStockQuery()
            ->whereIn('products.id', $productIds)
            ->get()
            ->keyBy(fn (Product $product): int => (int) $product->id);
    }

    public static function findPresentable(int $productId): ?Product
    {
        $product = self::inStockQuery()
            ->whereKey($productId)
            ->first();

        return $product instanceof Product ? $product : null;
    }

    /**
     * Categorías activas con productos disponibles.
     *
     * @return list<array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     image_url: string|null,
     *     product_count: int,
     *     is_medication: bool
     * }>
     */
    public static function categories(?int $limit = null): array
    {
        $key = self::cacheKey('categories.'.($limit ?? 'all'));

        return Cache::remember($key, self::TTL_CATEGORIES, function () use ($limit): array {
            $query = ProductCategory::query()
                ->where('is_active', true)
                ->withCount(['products as product_count' => function (Builder $query): void {
                    $query->where('is_active', true);
                }])
                ->having('product_count', '>', 0)
                ->orderByDesc('product_count')
                ->orderBy('name');

            if ($limit !== null) {
                $query->limit($limit);
            }

            return $query->get()
                ->map(fn (ProductCategory $category): array => [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                    'slug' => (string) ($category->slug ?: ''),
                    'image_url' => filled($category->image)
                        ? Storage::disk('public')->url((string) $category->image)
                        : null,
                    'product_count' => (int) ($category->product_count ?? 0),
                    'is_medication' => (bool) $category->is_medication,
                ])
                ->values()
                ->all();
        });
    }

    public static function findCategory(string $slugOrId): ?ProductCategory
    {
        $category = ProductCategory::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($slugOrId): void {
                $query->where('slug', $slugOrId);

                if (ctype_digit($slugOrId)) {
                    $query->orWhere('id', (int) $slugOrId);
                }
            })
            ->first();

        return $category instanceof ProductCategory ? $category : null;
    }

    /**
     * Bloque de portada (categorías, ofertas, más vendidos) en una sola lectura cacheada.
     *
     * @return array{categories: list<array<string, mixed>>, bestsellers: list<array<string, mixed>>, offers: list<array<string, mixed>>, hasOffers: bool}
     */
    public static function home(int $limit = 10): array
    {
        return Cache::remember(self::cacheKey('home.'.$limit), self::TTL_HOME, function () use ($limit): array {
            $offers = self::offers($limit);

            return [
                'categories' => self::categories($limit),
                'bestsellers' => self::bestsellers($limit),
                'offers' => $offers,
                'hasOffers' => $offers !== [],
            ];
        });
    }

    /**
     * Destacados de portada: lo más vendido con respaldo por mayor descuento.
     *
     * @return list<array<string, mixed>>
     */
    public static function bestsellers(int $limit = 12): array
    {
        return Cache::remember(self::cacheKey('bestsellers.'.$limit), self::TTL_HOME, function () use ($limit): array {
            $rankedIds = self::bestsellerProductIds($limit);

            if ($rankedIds !== []) {
                $ranked = self::present(
                    self::inStockQuery(listing: true)
                        ->whereIn('products.id', $rankedIds)
                        ->orderByRaw(self::caseOrderSql($rankedIds), $rankedIds)
                        ->limit($limit)
                        ->get(),
                );

                if ($ranked !== []) {
                    return $ranked;
                }
            }

            return self::present(
                self::inStockQuery(listing: true)
                    ->orderByDesc('products.discount_percent')
                    ->orderBy('products.name')
                    ->limit($limit)
                    ->get(),
            );
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function offers(int $limit = 12): array
    {
        return Cache::remember(self::cacheKey('offers.'.$limit), self::TTL_HOME, function () use ($limit): array {
            return self::present(
                self::inStockQuery(listing: true)
                    ->where('products.discount_percent', '>', 0)
                    ->orderByDesc('products.discount_percent')
                    ->orderBy('products.name')
                    ->limit($limit)
                    ->get(),
            );
        });
    }

    /**
     * Sugerencias dentro de la misma categoría, excluyendo el producto actual.
     *
     * @return list<array<string, mixed>>
     */
    public static function related(Product $product, int $limit = 8): array
    {
        $query = self::inStockQuery(listing: true)
            ->whereKeyNot($product->id)
            ->orderByDesc('products.discount_percent')
            ->orderBy('products.name')
            ->limit($limit);

        if ($product->product_category_id !== null) {
            $query->where('products.product_category_id', $product->product_category_id);
        }

        $related = self::present($query->get());

        if ($related !== []) {
            return $related;
        }

        return self::present(
            self::inStockQuery(listing: true)
                ->whereKeyNot($product->id)
                ->orderByDesc('products.discount_percent')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * Búsqueda y filtrado del catálogo.
     *
     * @param  'relevance'|'price_asc'|'price_desc'|'discount'  $sort
     * @return list<array<string, mixed>>
     */
    public static function search(
        string $term = '',
        ?int $categoryId = null,
        string $sort = 'relevance',
        bool $onlyOffers = false,
        int $limit = 30,
    ): array {
        $term = trim(mb_substr($term, 0, 80));

        $fingerprint = md5(implode('|', [$term, (string) $categoryId, $sort, $onlyOffers ? '1' : '0', (string) $limit]));

        return Cache::remember(self::cacheKey('search.'.$fingerprint), self::TTL_SEARCH, function () use ($term, $categoryId, $sort, $onlyOffers, $limit): array {
            return self::searchUncached($term, $categoryId, $sort, $onlyOffers, $limit);
        });
    }

    /**
     * @param  'relevance'|'price_asc'|'price_desc'|'discount'  $sort
     * @return list<array<string, mixed>>
     */
    private static function searchUncached(
        string $term,
        ?int $categoryId,
        string $sort,
        bool $onlyOffers,
        int $limit,
    ): array {
        [$term, $categoryId, $onlyOffers] = self::resolveBrowseIntent($term, $categoryId, $onlyOffers);

        $query = self::inStockQuery(listing: true);

        if ($term !== '') {
            $exactHits = self::exactCodeHits($query, $term, $limit);

            if ($exactHits !== []) {
                return $exactHits;
            }

            self::applyTextSearch($query, $term);
        }

        if ($categoryId !== null && $categoryId > 0) {
            $query->where('products.product_category_id', $categoryId);
        }

        if ($onlyOffers) {
            $query->where('products.discount_percent', '>', 0);
        }

        self::applySort($query, $sort, $term);

        return self::present($query->limit($limit)->get());
    }

    /**
     * Un clic en «Vitaminas» o escribir el nombre exacto de la categoría
     * debe listar esa categoría, no buscar un producto con ese nombre.
     *
     * @return array{0: string, 1: int|null, 2: bool}
     */
    private static function resolveBrowseIntent(string $term, ?int $categoryId, bool $onlyOffers): array
    {
        $categoryId = ($categoryId !== null && $categoryId > 0) ? $categoryId : null;

        if (self::foldedEquals($term, 'ofertas')) {
            return ['', $categoryId, true];
        }

        $matchedId = $term !== '' ? self::categoryIdIfExactLabel($term) : null;

        if ($matchedId !== null) {
            return ['', $matchedId, $onlyOffers];
        }

        return [$term, $categoryId, $onlyOffers];
    }

    private static function categoryIdIfExactLabel(string $term): ?int
    {
        $needle = self::foldLabel($term);

        if ($needle === '') {
            return null;
        }

        foreach (self::categories() as $category) {
            if (self::foldLabel($category['name']) === $needle || self::foldLabel($category['slug']) === $needle) {
                return $category['id'];
            }
        }

        return null;
    }

    private static function foldedEquals(string $left, string $right): bool
    {
        return self::foldLabel($left) === self::foldLabel($right);
    }

    private static function foldLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, [
            'á' => 'a',
            'à' => 'a',
            'ä' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ë' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'ï' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ö' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    /**
     * Código de barras o SKU exactos: usa índices únicos y evita LIKE.
     *
     * @param  Builder<Product>  $base
     * @return list<array<string, mixed>>
     */
    private static function exactCodeHits(Builder $base, string $term, int $limit): array
    {
        if (mb_strlen($term) < 4) {
            return [];
        }

        $hits = (clone $base)
            ->where(function (Builder $where) use ($term): void {
                $where->where('products.barcode', $term)
                    ->orWhere('products.sku', $term);
            })
            ->limit($limit)
            ->get();

        return $hits->isEmpty() ? [] : self::present($hits);
    }

    /**
     * @param  Builder<Product>  $query
     */
    private static function applyTextSearch(Builder $query, string $term): void
    {
        $escaped = addcslashes($term, '%_\\');
        $length = mb_strlen($term);
        $tokens = preg_split('/\s+/u', $term, 4, PREG_SPLIT_NO_EMPTY) ?: [$term];

        $query->where(function (Builder $where) use ($escaped, $length, $tokens): void {
            $where->where('products.name', 'like', $escaped.'%')
                ->orWhere('products.brand', 'like', $escaped.'%');

            if ($length >= 3) {
                $where->orWhere('products.name', 'like', '%'.$escaped.'%')
                    ->orWhere('products.brand', 'like', '%'.$escaped.'%')
                    ->orWhere('products.active_ingredient', 'like', '%'.$escaped.'%')
                    ->orWhereIn('products.product_category_id', function ($sub) use ($escaped): void {
                        $sub->select('id')
                            ->from('product_categories')
                            ->where('is_active', true)
                            ->where(function ($category) use ($escaped): void {
                                $category->where('name', 'like', $escaped.'%')
                                    ->orWhere('slug', 'like', $escaped.'%');
                            });
                    });

                foreach (array_slice($tokens, 1, 2) as $token) {
                    $token = addcslashes((string) $token, '%_\\');

                    if (mb_strlen($token) < 3) {
                        continue;
                    }

                    $where->orWhere('products.name', 'like', '%'.$token.'%');
                }
            }
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @param  'relevance'|'price_asc'|'price_desc'|'discount'  $sort
     */
    private static function applySort(Builder $query, string $sort, string $term): void
    {
        match ($sort) {
            'price_asc' => $query->orderByRaw('products.sale_price * (1 - products.discount_percent / 100) ASC'),
            'price_desc' => $query->orderByRaw('products.sale_price * (1 - products.discount_percent / 100) DESC'),
            'discount' => $query->orderByDesc('products.discount_percent')->orderBy('products.name'),
            default => $term !== ''
                ? $query->orderByRaw(
                    'CASE WHEN products.barcode = ? THEN 0 WHEN products.name LIKE ? THEN 1 ELSE 2 END',
                    [$term, addcslashes($term, '%_\\').'%'],
                )->orderBy('products.name')
                : $query->orderByDesc('products.discount_percent')->orderBy('products.name'),
        };
    }

    /**
     * @return Builder<Product>
     */
    public static function inStockQuery(bool $listing = false): Builder
    {
        $stockSub = Inventory::query()
            ->select('product_id')
            ->selectRaw('COALESCE(SUM(quantity - reserved_quantity), 0) as stock_available')
            ->groupBy('product_id')
            ->havingRaw('COALESCE(SUM(quantity - reserved_quantity), 0) > 1');

        $query = Product::query()
            ->joinSub($stockSub, 'stock', 'stock.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->with(['productCategory:id,name,slug']);

        if ($listing) {
            $query->select([
                'products.id',
                'products.name',
                'products.barcode',
                'products.sku',
                'products.brand',
                'products.presentation_type',
                'products.active_ingredient',
                'products.sale_price',
                'products.discount_percent',
                'products.applies_vat',
                'products.requires_prescription',
                'products.image',
                'products.product_category_id',
            ])->selectRaw('stock.stock_available as stock_available');
        } else {
            $query->select('products.*')
                ->selectRaw('stock.stock_available as stock_available');
        }

        return $query;
    }

    /**
     * @param  EloquentCollection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private static function present(EloquentCollection $products): array
    {
        return $products
            ->map(fn (Product $product): array => StorefrontProductPresenter::fromProduct($product))
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private static function bestsellerProductIds(int $limit): array
    {
        return Cache::remember(self::cacheKey('bestseller-ids.'.$limit), self::TTL_BESTSELLERS, function () use ($limit): array {
            return SaleItem::query()
                ->select('sale_items.product_id')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.status', SaleStatus::Completed)
                ->whereNotNull('sale_items.product_id')
                ->groupBy('sale_items.product_id')
                ->orderByRaw('SUM(sale_items.quantity) DESC')
                ->limit($limit)
                ->pluck('product_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        });
    }

    /**
     * @param  list<int>  $ids
     */
    private static function caseOrderSql(array $ids): string
    {
        $whens = [];

        foreach (array_values($ids) as $index => $id) {
            $whens[] = 'WHEN ? THEN '.$index;
        }

        return 'CASE products.id '.implode(' ', $whens).' ELSE 999 END';
    }

    private static function cacheKey(string $name): string
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);

        return 'shop.cat.v'.$version.'.'.$name;
    }
}
