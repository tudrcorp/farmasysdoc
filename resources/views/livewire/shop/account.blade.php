@php
    $shopCustomer = \App\Models\ShopCustomer::current();
    $firstOrder = $orders->first();
    $customerName = $shopCustomer?->fullName() ?: $firstOrder?->delivery_recipient_name;
@endphp

<div>
    @include('shop.partials.header', ['title' => 'Mi cuenta'])

    <main class="sh-main">
        <div class="sh-page" style="padding-top:1rem;">
            {{-- Cabecera de cuenta --}}
            <div class="sh-account-head">
                <span class="sh-avatar" aria-hidden="true">
                    @if ($shopCustomer)
                        {{ $shopCustomer->initials() }}
                    @elseif ($customerName)
                        {{ \Illuminate\Support\Str::of($customerName)->substr(0, 1)->upper() }}
                    @else
                        @include('shop.partials.icon', ['icon' => 'user'])
                    @endif
                </span>

                <div style="min-width:0;flex:1;">
                    <strong style="display:block;font-size:1.02rem;letter-spacing:-0.024em;">
                        {{ $customerName ?? 'Hola 👋' }}
                    </strong>
                    <span style="font-size:0.8rem;color:var(--sh-muted);">
                        @if ($shopCustomer?->email)
                            {{ $shopCustomer->email }}
                        @elseif ($orders->isEmpty())
                            Aún no tienes pedidos
                        @else
                            {{ $orders->count() }} {{ \Illuminate\Support\Str::plural('pedido', $orders->count()) }}
                            · ${{ number_format($totalSpent, 2) }}
                        @endif
                    </span>
                </div>
            </div>

            {{-- Pedidos --}}
            <p class="sh-sheet__label">Mis pedidos</p>

            @if ($orders->isEmpty())
                <div class="sh-empty" style="padding-block:2.5rem;">
                    <span class="sh-empty__art" aria-hidden="true">
                        @include('shop.partials.icon', ['icon' => 'bag'])
                    </span>
                    <h3>Sin pedidos todavía</h3>
                    <p>Cuando hagas tu primer pedido lo verás aquí con su seguimiento.</p>
                    <a href="{{ route('shop.search') }}" wire:navigate class="sh-btn sh-btn--primary" style="margin-top:1rem;">
                        Explorar productos
                    </a>
                </div>
            @else
                <div class="sh-form">
                    @foreach ($orders as $order)
                        <a
                            href="{{ route('shop.order', $order->order_number) }}"
                            wire:navigate
                            class="sh-order"
                            wire:key="order-{{ $order->id }}"
                        >
                            <span class="sh-option__icon" aria-hidden="true">
                                @include('shop.partials.icon', ['icon' => 'bag'])
                            </span>

                            <div class="sh-order__copy">
                                <strong>{{ $order->order_number }}</strong>
                                <span>
                                    {{ $order->created_at?->translatedFormat('d M Y') }}
                                    · {{ (int) $order->items->sum('quantity') }} {{ \Illuminate\Support\Str::plural('unidad', (int) $order->items->sum('quantity')) }}
                                </span>
                            </div>

                            <div class="sh-order__amount">
                                <strong>${{ number_format((float) $order->total, 2) }}</strong>
                                <span @class([
                                    'sh-pill',
                                    'sh-pill--ok' => $order->status?->value === 'finalizado',
                                    'sh-pill--warn' => $order->status?->value !== 'finalizado',
                                ])>
                                    {{ $order->status?->label() ?? 'Pendiente' }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Ayuda --}}
            <p class="sh-sheet__label">Ayuda</p>

            <div class="sh-form">
                <a
                    href="https://wa.me/584127018390"
                    class="sh-sheet__item"
                >
                    <span class="sh-sheet__icon sh-sheet__icon--teal" aria-hidden="true">
                        @include('shop.partials.icon', ['icon' => 'whatsapp'])
                    </span>
                    <span class="sh-sheet__copy">
                        <strong>Atención al cliente</strong>
                        <span>0412-701-8390</span>
                    </span>
                    <span class="sh-sheet__chevron" aria-hidden="true">
                        @include('shop.partials.icon', ['icon' => 'chevron'])
                    </span>
                </a>
            </div>

            <template x-if="$store.shop.canInstall">
                <button
                    type="button"
                    class="sh-btn sh-btn--yellow sh-btn--block"
                    style="margin-top:1.15rem;"
                    @click="$store.shop.install()"
                >
                    @include('shop.partials.icon', ['icon' => 'download'])
                    Instalar Farmadoc en tu teléfono
                </button>
            </template>

            <form method="POST" action="{{ route('shop.logout') }}" class="sh-logout-form">
                @csrf
                <button type="submit" class="sh-logout">
                    @include('shop.partials.icon', ['icon' => 'logout'])
                    Cerrar sesión
                </button>
            </form>

            <div
                class="sh-note sh-note--info"
                style="margin-top:1.15rem;"
                x-show="$store.shop.showIosInstall"
                x-cloak
            >
                @include('shop.partials.icon', ['icon' => 'download'])
                <span>En Safari: toca <strong>Compartir</strong> y luego <strong>Añadir a pantalla de inicio</strong>.</span>
            </div>
        </div>
    </main>
</div>
