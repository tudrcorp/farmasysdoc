<?php

namespace App\Filament\GlobalSearch;

use App\Enums\SaleStatus;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use Carbon\Carbon;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

final class FarmaadminGlobalSearchProvider implements GlobalSearchProvider
{
    private const PRODUCT_LIMIT = 18;

    private const CLIENT_LIMIT = 12;

    private static ?bool $productsTableHasSku = null;

    private static ?bool $productsTableHasSlug = null;

    public function getResults(string $query): ?GlobalSearchResults
    {
        $term = trim($query);

        if ($term === '') {
            return null;
        }

        if (mb_strlen($term) > 500) {
            $term = mb_substr($term, 0, 500);
        }

        $builder = GlobalSearchResults::make();

        $productResults = $this->productGlobalSearchResults($term);
        if ($productResults->isNotEmpty()) {
            $builder->category('Productos', $productResults->all());
        }

        $clientResults = $this->clientGlobalSearchResults($term);
        if ($clientResults->isNotEmpty()) {
            $builder->category('Clientes', $clientResults->all());
        }

        $default = app(DefaultGlobalSearchProvider::class)->getResults($term);

        if ($default !== null) {
            $this->mergeDefaultCategories($builder, $default);
        }

        return $builder;
    }

    /**
     * @return Collection<int, GlobalSearchResult>
     */
    private function productGlobalSearchResults(string $term): Collection
    {
        if (! ProductResource::canAccess()) {
            return collect();
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $hasSku = $this->productsTableHasSkuColumn();
        $hasSlug = $this->productsTableHasSlugColumn();

        $select = [
            'id',
            'name',
            'barcode',
            'brand',
            'is_active',
            'active_ingredient',
            'description',
            'manufacturer',
            'sale_price',
        ];

        if ($hasSku) {
            $select[] = 'sku';
        }

        if ($hasSlug) {
            $select[] = 'slug';
        }

        $products = Product::query()
            ->select($select)
            ->where(function ($q) use ($like, $term, $hasSku, $hasSlug): void {
                $q->where('name', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('manufacturer', 'like', $like)
                    ->orWhere('description', 'like', $like);

                if ($hasSku) {
                    $q->orWhere('sku', 'like', $like);
                }

                if ($hasSlug) {
                    $q->orWhere('slug', 'like', $like);
                }

                if ($term !== '') {
                    $q->orWhere(function ($q2) use ($term): void {
                        $q2->whereActiveIngredientContains($term);
                    });
                }
            })
            ->orderBy('name')
            ->limit(self::PRODUCT_LIMIT)
            ->get();

        $productIds = $products->pluck('id')->all();

        /** @var array<int, float> */
        $stockByProduct = $productIds === []
            ? []
            : Inventory::query()
                ->whereIn('product_id', $productIds)
                ->selectRaw('product_id, SUM(quantity) as qty')
                ->groupBy('product_id')
                ->pluck('qty', 'product_id')
                ->map(fn (mixed $q): float => (float) $q)
                ->all();

        /** @var array<int, array<int, array{name: string, qty: float}>> */
        $stockByBranchPerProduct = [];

        if ($productIds !== []) {
            $inventories = Inventory::query()
                ->whereIn('product_id', $productIds)
                ->with('branch:id,name')
                ->get(['product_id', 'branch_id', 'quantity']);

            foreach ($inventories as $inv) {
                $pid = (int) $inv->product_id;
                $bid = (int) $inv->branch_id;

                if (! isset($stockByBranchPerProduct[$pid])) {
                    $stockByBranchPerProduct[$pid] = [];
                }

                if (! isset($stockByBranchPerProduct[$pid][$bid])) {
                    $stockByBranchPerProduct[$pid][$bid] = [
                        'name' => filled($inv->branch?->name)
                            ? (string) $inv->branch->name
                            : 'Sucursal #'.$bid,
                        'qty' => 0.0,
                    ];
                }

                $stockByBranchPerProduct[$pid][$bid]['qty'] += (float) $inv->quantity;
            }
        }

        return $products->filter(function (Product $product): bool {
            return ProductResource::canView($product);
        })->map(function (Product $product) use ($stockByProduct, $stockByBranchPerProduct, $hasSku, $hasSlug): GlobalSearchResult {
            $url = ProductResource::getUrl('view', ['record' => $product], isAbsolute: false);
            $stock = $stockByProduct[$product->id] ?? 0.0;

            $details = [
                'Código / barras' => filled($product->barcode) ? (string) $product->barcode : '—',
            ];

            if ($hasSku) {
                $details['SKU'] = filled($product->sku) ? (string) $product->sku : '—';
            }

            if ($hasSlug) {
                $details['Slug'] = filled($product->slug) ? (string) $product->slug : '—';
            }

            $details = array_merge($details, [
                'Marca' => filled($product->brand) ? (string) $product->brand : '—',
                'Principio activo' => $this->formatActiveIngredient($product->active_ingredient),
                'Precio venta' => '$'.number_format((float) ($product->sale_price ?? 0), 2, '.', ','),
            ]);

            $branches = $stockByBranchPerProduct[$product->id] ?? [];

            if ($branches === []) {
                $details['Existencia por sucursal'] = 'Sin registros de inventario';
            } else {
                uasort(
                    $branches,
                    fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']),
                );

                foreach ($branches as $data) {
                    $details['Exist. '.$data['name']] = $this->formatQuantity($data['qty']);
                }
            }

            $details['Stock total (todas las sucursales)'] = new HtmlString(
                '<span class="font-semibold text-primary-600 dark:text-primary-400">'
                .e($this->formatQuantity($stock))
                .'</span>',
            );

            $details['Estado'] = $product->is_active ? 'Activo' : 'Inactivo';

            $title = filled($product->barcode)
                ? $product->name.' · '.$product->barcode
                : $product->name;

            return new GlobalSearchResult(
                title: $title,
                url: $url,
                details: $details,
            );
        });
    }

    /**
     * @return Collection<int, GlobalSearchResult>
     */
    private function clientGlobalSearchResults(string $term): Collection
    {
        if (! ClientResource::canAccess()) {
            return collect();
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $clients = Client::query()
            ->select([
                'id',
                'name',
                'document_type',
                'document_number',
                'email',
                'phone',
                'city',
                'status',
            ])
            ->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('document_number', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderBy('name')
            ->limit(self::CLIENT_LIMIT)
            ->get()
            ->filter(fn (Client $client): bool => ClientResource::canView($client));

        if ($clients->isEmpty()) {
            return collect();
        }

        $ids = $clients->pluck('id')->all();

        $saleStats = Sale::query()
            ->whereIn('client_id', $ids)
            ->where('status', SaleStatus::Completed)
            ->selectRaw('client_id, COUNT(*) as sale_count, COALESCE(SUM(total), 0) as total_usd, MAX(sold_at) as last_sold_at')
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');

        $allSalesCount = Sale::query()
            ->whereIn('client_id', $ids)
            ->selectRaw('client_id, COUNT(*) as c')
            ->groupBy('client_id')
            ->pluck('c', 'client_id');

        return $clients->map(function (Client $client) use ($saleStats, $allSalesCount): GlobalSearchResult {
            $url = ClientResource::getUrl('view', ['record' => $client], isAbsolute: false);
            $stat = $saleStats->get($client->id);
            $totalSales = (int) ($allSalesCount[$client->id] ?? 0);

            $doc = trim(implode(' ', array_filter([(string) ($client->document_type ?? ''), (string) ($client->document_number ?? '')])));

            $details = [
                'Documento' => $doc !== '' ? $doc : '—',
                'Teléfono' => filled($client->phone) ? (string) $client->phone : '—',
                'Email' => filled($client->email) ? (string) $client->email : '—',
                'Ciudad' => filled($client->city) ? (string) $client->city : '—',
                'Estado cliente' => filled($client->status) ? (string) $client->status : '—',
                'Ventas registradas (todas)' => (string) $totalSales,
                'Ventas completadas' => $stat ? (string) (int) $stat->sale_count : '0',
                'Total ventas completadas (USD)' => '$'.number_format($stat ? (float) $stat->total_usd : 0.0, 2, '.', ','),
                'Última venta' => $stat && $stat->last_sold_at
                    ? Carbon::parse($stat->last_sold_at)->format('d/m/Y H:i')
                    : '—',
            ];

            $title = filled($client->document_number)
                ? $client->name.' · '.$client->document_number
                : $client->name;

            return new GlobalSearchResult(
                title: $title,
                url: $url,
                details: $details,
            );
        });
    }

    private function mergeDefaultCategories(GlobalSearchResults $builder, GlobalSearchResults $default): void
    {
        foreach ($default->getCategories() as $name => $items) {
            $existing = $builder->getCategories()->get($name);

            if ($existing === null) {
                $builder->category($name, is_array($items) ? $items : $items->all());

                continue;
            }

            $merged = collect($existing)
                ->merge(is_array($items) ? $items : $items->all())
                ->values()
                ->all();

            $builder->category($name, $merged);
        }
    }

    private function formatQuantity(float $quantity): string
    {
        $formatted = rtrim(rtrim(number_format($quantity, 3, '.', ','), '0'), '.');

        return $formatted !== '' ? $formatted : '0';
    }

    private function formatActiveIngredient(mixed $value): string
    {
        if (is_array($value)) {
            $flat = [];

            foreach ($value as $item) {
                if (is_string($item) && $item !== '') {
                    $flat[] = $item;
                } elseif (is_array($item)) {
                    $flat[] = implode(' ', array_filter(array_map('strval', $item)));
                }
            }

            $joined = implode(', ', array_filter($flat));

            return $joined !== '' ? $joined : '—';
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return '—';
    }

    private function productsTableHasSkuColumn(): bool
    {
        return self::$productsTableHasSku ??= Schema::hasColumn('products', 'sku');
    }

    private function productsTableHasSlugColumn(): bool
    {
        return self::$productsTableHasSlug ??= Schema::hasColumn('products', 'slug');
    }
}
