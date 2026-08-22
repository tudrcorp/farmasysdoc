@php
    $isPickup = str_contains((string) $order->notes, 'Retiro en sucursal');
    $itemCount = (int) $order->items->sum('quantity');
@endphp

<div>
    <main class="sh-main sh-main--action" style="padding-top:calc(var(--sh-safe-top) + 1.5rem);">
        <div class="sh-page">
            <div class="sh-done">
                <div class="sh-done__ring" aria-hidden="true">
                    <span class="sh-done__mark">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </span>
                </div>

                <h1 class="sh-done__title">¡Gracias por tu compra!</h1>

                <p class="sh-sub" style="margin-top:0.7rem;max-width:28ch;">
                    Tu pedido está confirmado y en proceso. Te contactamos enseguida para coordinarlo.
                </p>

                <button
                    type="button"
                    class="sh-done__code"
                    x-data
                    @click="navigator.clipboard?.writeText(@js($order->order_number)); window.dispatchEvent(new CustomEvent('shop-toast', { detail: { message: 'Número copiado' } }))"
                >
                    @include('shop.partials.icon', ['icon' => 'tag'])
                    {{ $order->order_number }}
                </button>
            </div>

            {{-- Detalle --}}
            <div class="sh-summary" style="margin-top:1.6rem;">
                <div class="sh-summary__row">
                    <span>Estado</span>
                    <strong>
                        <span class="sh-pill sh-pill--warn">{{ $order->status?->label() ?? 'Pendiente' }}</span>
                    </strong>
                </div>

                <div class="sh-summary__row">
                    <span>{{ $isPickup ? 'Retiras en' : 'Enviamos a' }}</span>
                    <strong style="text-align:right;max-width:62%;font-size:0.82rem;">
                        {{ $isPickup
                            ? ($order->branch?->name ?? 'Sucursal')
                            : \Illuminate\Support\Str::limit((string) $order->delivery_address, 44) }}
                    </strong>
                </div>

                <div class="sh-summary__row">
                    <span>Recibe</span>
                    <strong style="font-size:0.82rem;">{{ $order->delivery_recipient_name }}</strong>
                </div>

                <div class="sh-summary__rule"></div>

                <div class="sh-summary__row">
                    <span>{{ $itemCount }} {{ \Illuminate\Support\Str::plural('unidad', $itemCount) }}</span>
                    <strong>${{ number_format((float) $order->subtotal - (float) $order->tax_total, 2) }}</strong>
                </div>

                @if ((float) $order->discount_total > 0)
                    <div class="sh-summary__row sh-summary__row--discount">
                        <span>Descuentos</span>
                        <strong>-${{ number_format((float) $order->discount_total, 2) }}</strong>
                    </div>
                @endif

                @if ((float) $order->tax_total > 0)
                    <div class="sh-summary__row">
                        <span>IVA</span>
                        <strong>${{ number_format((float) $order->tax_total, 2) }}</strong>
                    </div>
                @endif

                <div class="sh-summary__rule"></div>

                <div class="sh-summary__row sh-summary__row--total">
                    <span>Total</span>
                    <strong>${{ number_format((float) $order->total, 2) }}</strong>
                </div>
            </div>

            {{-- Productos --}}
            <p class="sh-sheet__label">Tu pedido</p>

            <div class="sh-lines">
                @foreach ($order->items as $item)
                    <article class="sh-line" wire:key="oi-{{ $item->id }}">
                        <span class="sh-line__media" aria-hidden="true">
                            @include('shop.partials.icon', ['icon' => 'box'])
                        </span>

                        <div class="sh-line__body">
                            <p class="sh-line__name">{{ $item->product_name_snapshot }}</p>
                            <p class="sh-line__meta">
                                {{ (int) $item->quantity }} × ${{ number_format((float) $item->unit_price, 2) }}
                            </p>
                        </div>

                        <span class="sh-line__sum">${{ number_format((float) $item->line_total, 2) }}</span>
                    </article>
                @endforeach
            </div>

            @if ($order->branch)
                <div class="sh-note sh-note--info" style="margin-top:1.15rem;">
                    @include('shop.partials.icon', ['icon' => 'store'])
                    <span>
                        <strong>{{ $order->branch->name }}</strong><br>
                        {{ $order->branch->address }}
                        @if ($order->branch->phone)
                            · {{ $order->branch->phone }}
                        @endif
                    </span>
                </div>
            @endif

            <a
                href="https://wa.me/584127018390?text={{ urlencode('Hola, consulto por mi pedido '.$order->order_number) }}"
                class="sh-btn sh-btn--ghost sh-btn--block"
                style="margin-top:1rem;"
            >
                @include('shop.partials.icon', ['icon' => 'whatsapp'])
                Consultar por WhatsApp
            </a>
        </div>
    </main>

    <div class="sh-actionbar">
        <a href="{{ route('shop.home') }}" wire:navigate class="sh-btn sh-btn--primary sh-btn--block">
            Seguir comprando
        </a>
    </div>
</div>
