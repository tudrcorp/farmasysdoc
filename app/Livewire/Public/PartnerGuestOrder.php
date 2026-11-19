<?php

namespace App\Livewire\Public;

use App\Enums\OrderFulfillmentType;
use App\Models\PartnerCompany;
use App\Models\Product;
use App\Services\Orders\PartnerGuestOrderPlacer;
use App\Support\Inventory\LasDeliciasBranch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.partner-guest')]
#[Title('Pedido aliado')]
class PartnerGuestOrder extends Component
{
    public string $step = 'code';

    public string $partnerCode = '';

    #[Locked]
    public ?int $partnerCompanyId = null;

    public string $companyName = '';

    public string $companyCode = '';

    public string $search = '';

    /** @var list<array{id: int, name: string, code: string, ingredient: string}> */
    public array $results = [];

    /** @var list<array{product_id: int, name: string, code: string, ingredient: string, quantity: int}> */
    public array $lines = [];

    public bool $isWholesale = false;

    public string $fulfillment = 'delivery';

    public string $recipientName = '';

    public string $recipientPhone = '';

    public string $recipientDocument = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $deliveryNotes = '';

    public ?string $orderNumber = null;

    public ?string $pdfUrl = null;

    public function unlock(): void
    {
        $this->validate([
            'partnerCode' => ['required', 'string', 'max:255'],
        ], [
            'partnerCode.required' => 'Escriba el código de su empresa.',
        ]);

        $this->ensureNotRateLimited('partner-guest-code');

        $code = mb_strtoupper(trim($this->partnerCode));
        $company = PartnerCompany::query()
            ->whereRaw('UPPER(code) = ?', [$code])
            ->where('is_active', true)
            ->where('can_place_orders', true)
            ->first();

        if (! $company instanceof PartnerCompany) {
            throw ValidationException::withMessages([
                'partnerCode' => 'No encontramos un aliado activo con ese código, o todavía no tiene pedidos habilitados.',
            ]);
        }

        if (LasDeliciasBranch::resolve() === null) {
            throw ValidationException::withMessages([
                'partnerCode' => 'El inventario de Las Delicias no está disponible en este momento.',
            ]);
        }

        $this->partnerCompanyId = (int) $company->getKey();
        $this->companyName = filled($company->trade_name) ? (string) $company->trade_name : (string) $company->legal_name;
        $this->companyCode = (string) $company->code;
        $this->step = 'order';
        $this->resetErrorBag();
    }

    public function updatedSearch(): void
    {
        $this->results = $this->searchProducts($this->search);
    }

    public function addProduct(int $productId): void
    {
        $this->ensureCompany();

        if (count($this->lines) >= PartnerGuestOrderPlacer::MAX_LINES) {
            $this->addError('lines', 'Puede incluir hasta '.PartnerGuestOrderPlacer::MAX_LINES.' productos por pedido.');

            return;
        }

        foreach ($this->lines as $index => $line) {
            if ((int) $line['product_id'] === $productId) {
                $this->lines[$index]['quantity'] = min(9999, (int) $line['quantity'] + 1);

                return;
            }
        }

        $product = $this->catalogProduct($productId);

        if (! $product instanceof Product) {
            $this->addError('lines', 'Ese producto ya no está en Las Delicias.');

            return;
        }

        $this->lines[] = [
            'product_id' => (int) $product->id,
            'name' => (string) $product->name,
            'code' => $this->codeLabel($product),
            'ingredient' => $this->ingredientLabel($product),
            'quantity' => 1,
        ];
        $this->resetErrorBag('lines');
    }

    public function incrementProduct(int $productId): void
    {
        foreach ($this->lines as $index => $line) {
            if ((int) $line['product_id'] === $productId) {
                $this->lines[$index]['quantity'] = min(9999, (int) $line['quantity'] + 1);

                return;
            }
        }

        $this->addProduct($productId);
    }

    public function decrementProduct(int $productId): void
    {
        foreach ($this->lines as $index => $line) {
            if ((int) $line['product_id'] !== $productId) {
                continue;
            }

            if ((int) $line['quantity'] <= 1) {
                $this->removeLine($index);

                return;
            }

            $this->lines[$index]['quantity'] = (int) $line['quantity'] - 1;

            return;
        }
    }

    public function updatedLines(mixed $value, string $key): void
    {
        if (! str_ends_with($key, '.quantity')) {
            return;
        }

        $index = (int) str($key)->before('.');

        if (! isset($this->lines[$index])) {
            return;
        }

        $amount = is_numeric($value) ? (int) $value : 1;
        $this->lines[$index]['quantity'] = min(9999, max(1, $amount));
    }

    public function removeLine(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function place(): void
    {
        $company = $this->ensureCompany();
        $this->ensureNotRateLimited('partner-guest-place', 6);

        $this->validate([
            'lines' => ['required', 'array', 'min:1', 'max:'.PartnerGuestOrderPlacer::MAX_LINES],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'fulfillment' => ['required', Rule::enum(OrderFulfillmentType::class)],
            'recipientName' => ['required', 'string', 'max:255'],
            'recipientPhone' => ['required', 'string', 'max:40'],
            'recipientDocument' => ['nullable', 'string', 'max:64'],
            'address' => [Rule::requiredIf($this->fulfillment === OrderFulfillmentType::Delivery->value), 'nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'deliveryNotes' => ['nullable', 'string', 'max:2000'],
        ], [
            'lines.required' => 'Agregue al menos un producto.',
            'lines.min' => 'Agregue al menos un producto.',
            'recipientName.required' => 'Indique quién recibe el pedido.',
            'recipientPhone.required' => 'Indique un teléfono de contacto.',
            'address.required' => 'Indique la dirección de entrega.',
        ]);

        $order = app(PartnerGuestOrderPlacer::class)->place($company, [
            'lines' => $this->lines,
            'is_wholesale' => $this->isWholesale,
            'fulfillment' => $this->fulfillment,
            'recipient_name' => $this->recipientName,
            'recipient_phone' => $this->recipientPhone,
            'recipient_document' => $this->recipientDocument,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'delivery_notes' => $this->deliveryNotes,
        ]);

        $this->orderNumber = (string) $order->order_number;
        $this->pdfUrl = URL::temporarySignedRoute(
            'public.partner-order.pdf',
            now()->addHours(48),
            ['order' => $order->getKey()],
        );
        $this->step = 'done';
        $this->lines = [];
        $this->results = [];
        $this->search = '';
    }

    public function another(): void
    {
        $this->step = 'order';
        $this->orderNumber = null;
        $this->pdfUrl = null;
        $this->resetErrorBag();
    }

    public function leave(): void
    {
        $this->reset();
        $this->step = 'code';
        $this->fulfillment = OrderFulfillmentType::Delivery->value;
    }

    /**
     * @return array{count: int}
     */
    public function summary(): array
    {
        return [
            'count' => count($this->lines),
        ];
    }

    public function render(): View
    {
        return view('livewire.public.partner-guest-order', [
            'summary' => $this->summary(),
            'quantities' => collect($this->lines)->mapWithKeys(
                fn (array $line): array => [(int) $line['product_id'] => (int) $line['quantity']],
            )->all(),
        ]);
    }

    private function ensureCompany(): PartnerCompany
    {
        $company = $this->partnerCompanyId === null
            ? null
            : PartnerCompany::query()->find($this->partnerCompanyId);

        if (! $company instanceof PartnerCompany || ! $company->is_active || ! $company->can_place_orders) {
            $this->step = 'code';

            throw ValidationException::withMessages([
                'partnerCode' => 'El acceso de este aliado ya no está disponible. Vuelva a escribir el código.',
            ]);
        }

        return $company;
    }

    private function ensureNotRateLimited(string $prefix, int $max = 10): void
    {
        $key = $prefix.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'partnerCode' => 'Demasiados intentos. Espere un momento e intente de nuevo.',
            ]);
        }

        RateLimiter::hit($key, 60);
    }

    /**
     * @return list<array{id: int, name: string, code: string, ingredient: string}>
     */
    private function searchProducts(string $term): array
    {
        if ($this->partnerCompanyId === null) {
            return [];
        }

        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $branchId = LasDeliciasBranch::resolve()?->getKey();

        if ($branchId === null) {
            return [];
        }

        $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';

        return Product::query()
            ->where('is_active', true)
            ->whereHas('inventories', fn ($query) => $query->where('branch_id', $branchId))
            ->where(function ($query) use ($like): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(active_ingredient, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'code' => $this->codeLabel($product),
                'ingredient' => $this->ingredientLabel($product),
            ])
            ->all();
    }

    private function catalogProduct(int $productId): ?Product
    {
        $branchId = LasDeliciasBranch::resolve()?->getKey();

        if ($branchId === null) {
            return null;
        }

        return Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->whereHas('inventories', fn ($query) => $query->where('branch_id', $branchId))
            ->first();
    }

    private function codeLabel(Product $product): string
    {
        $sku = trim((string) ($product->sku ?? ''));
        $barcode = trim((string) ($product->barcode ?? ''));

        if ($sku !== '' && $barcode !== '' && $sku !== $barcode) {
            return $barcode;
        }

        return $barcode !== '' ? $barcode : ($sku !== '' ? $sku : '—');
    }

    private function ingredientLabel(Product $product): string
    {
        $values = array_values(array_filter(
            array_map('strval', is_array($product->active_ingredient) ? $product->active_ingredient : [$product->active_ingredient]),
            fn (string $value): bool => trim($value) !== '',
        ));

        return $values === [] ? '—' : implode(', ', $values);
    }
}
