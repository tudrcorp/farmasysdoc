<div x-data="{ emptyOpen: false }">
    @include('shop.partials.header', [
        'title' => 'Mi carrito',
        'showCart' => false,
    ])

    <main @class(['sh-main', 'sh-main--action' => $lines !== []])>
        <div class="sh-page" style="padding-top:1rem;">
            @if ($lines === [])
                <div class="sh-empty" style="padding-top:4rem;">
                    <span class="sh-empty__art" aria-hidden="true">
                        @include('shop.partials.icon', ['icon' => 'cart'])
                    </span>
                    <h3>Tu carrito está vacío</h3>
                    <p>Agrega productos y aparecerán aquí listos para pedir.</p>
                    <a href="{{ route('shop.search') }}" wire:navigate class="sh-btn sh-btn--primary" style="margin-top:1rem;">
                        Explorar productos
                    </a>
                </div>
            @else
                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;">
                    <p class="sh-sub" style="margin:0;">
                        {{ $totals['units'] }} {{ \Illuminate\Support\Str::plural('unidad', $totals['units']) }}
                        · {{ count($lines) }} {{ \Illuminate\Support\Str::plural('producto', count($lines)) }}
                    </p>

                    <button type="button" class="sh-btn sh-btn--danger" style="min-height:2.3rem;padding-inline:0.85rem;" @click="emptyOpen = true">
                        @include('shop.partials.icon', ['icon' => 'trash'])
                        Vaciar
                    </button>
                </div>

                {{-- Líneas --}}
                <div class="sh-lines" style="margin-top:0.6rem;">
                    @foreach ($lines as $line)
                        <div
                            class="sh-line-wrap"
                            wire:key="line-{{ $line['product_id'] }}"
                            x-data="shopSwipe({{ $line['product_id'] }})"
                        >
                            <button
                                type="button"
                                class="sh-line-wrap__delete"
                                wire:click="removeFromCart({{ $line['product_id'] }})"
                                aria-label="Eliminar {{ $line['product']['name'] }}"
                                tabindex="-1"
                            >
                                @include('shop.partials.icon', ['icon' => 'trash'])
                            </button>

                            <article
                                class="sh-line"
                                :class="{ 'is-swiping': swiping }"
                                :style="style()"
                                @pointerdown="start($event)"
                                @pointermove="move($event)"
                                @pointerup="end()"
                                @pointercancel="reset()"
                            >
                                <a
                                    href="{{ route('shop.product', $line['product_id']) }}"
                                    wire:navigate
                                    class="sh-line__media"
                                    aria-label="Ver {{ $line['product']['name'] }}"
                                >
                                    <img src="{{ $line['product']['image_url'] }}" alt="" loading="lazy">
                                </a>

                                <div class="sh-line__body">
                                    <a href="{{ route('shop.product', $line['product_id']) }}" wire:navigate class="sh-line__name">
                                        {{ $line['product']['name'] }}
                                    </a>

                                    <p class="sh-line__meta">
                                        {{ $money->formatUsd($line['unit_price']) }} c/u
                                        @if ($money->formatVes($line['unit_price']))
                                            · {{ $money->formatVes($line['unit_price']) }}
                                        @endif
                                        @if ($line['discount_percent'] > 0)
                                            · <span style="color:var(--sh-success);font-weight:650;">-{{ (int) $line['discount_percent'] }}%</span>
                                        @endif
                                    </p>

                                    <div class="sh-line__foot">
                                        <div class="sh-stepper">
                                            <button
                                                type="button"
                                                wire:click="decrement({{ $line['product_id'] }})"
                                                aria-label="Quitar una unidad"
                                            >
                                                @include('shop.partials.icon', ['icon' => 'minus'])
                                            </button>
                                            <span class="sh-stepper__value">{{ (int) $line['quantity'] }}</span>
                                            <button
                                                type="button"
                                                wire:click="increment({{ $line['product_id'] }})"
                                                @disabled($line['quantity'] >= $line['stock_available'])
                                                aria-label="Agregar una unidad"
                                            >
                                                @include('shop.partials.icon', ['icon' => 'plus'])
                                            </button>
                                        </div>

                                        <span class="sh-line__sum sh-money">
                                            <strong>{{ $money->formatUsd($line['line_total']) }}</strong>
                                            @if ($money->formatVes($line['line_total']))
                                                <span class="sh-money__ves">{{ $money->formatVes($line['line_total']) }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>

                @if ($requiresPrescription)
                    <div class="sh-note" style="margin-top:1rem;">
                        @include('shop.partials.icon', ['icon' => 'rx'])
                        <span>Tu pedido incluye productos con récipe médico. Ten el récipe a mano al recibirlo.</span>
                    </div>
                @endif

                {{-- Resumen --}}
                <div class="sh-summary" style="margin-top:1.15rem;">
                    <div class="sh-summary__row">
                        <span>Subtotal</span>
                        <span class="sh-money">
                            <strong>{{ $money->formatUsd($totals['net']) }}</strong>
                            @if ($money->formatVes($totals['net']))
                                <span class="sh-money__ves">{{ $money->formatVes($totals['net']) }}</span>
                            @endif
                        </span>
                    </div>

                    @if ($totals['discount'] > 0)
                        <div class="sh-summary__row sh-summary__row--discount">
                            <span>Descuentos</span>
                            <span class="sh-money">
                                <strong>-{{ $money->formatUsd($totals['discount']) }}</strong>
                                @if ($money->formatVes($totals['discount']))
                                    <span class="sh-money__ves">-{{ $money->formatVes($totals['discount']) }}</span>
                                @endif
                            </span>
                        </div>
                    @endif

                    @if ($totals['tax'] > 0)
                        <div class="sh-summary__row">
                            <span>IVA</span>
                            <span class="sh-money">
                                <strong>{{ $money->formatUsd($totals['tax']) }}</strong>
                                @if ($money->formatVes($totals['tax']))
                                    <span class="sh-money__ves">{{ $money->formatVes($totals['tax']) }}</span>
                                @endif
                            </span>
                        </div>
                    @endif

                    <div class="sh-summary__rule"></div>

                    <div class="sh-summary__row sh-summary__row--total">
                        <span>Total</span>
                        <span class="sh-money">
                            <strong>{{ $money->formatUsd($totals['total']) }}</strong>
                            @if ($money->formatVes($totals['total']))
                                <span class="sh-money__ves">{{ $money->formatVes($totals['total']) }}</span>
                            @endif
                        </span>
                    </div>

                    @if ($money->rate())
                        <p class="sh-summary__ves">Tasa BCV · Bs. {{ number_format($money->rate(), 2, ',', '.') }}</p>
                    @endif
                </div>
            @endif
        </div>
    </main>

    @if ($lines !== [])
        <div class="sh-actionbar" x-show="! emptyOpen">
            <div class="sh-actionbar__row">
                <div class="sh-actionbar__total">
                    <span>Total</span>
                    <strong>{{ $money->formatUsd($totals['total']) }}</strong>
                    @if ($money->formatVes($totals['total']))
                        <em class="sh-money__ves">{{ $money->formatVes($totals['total']) }}</em>
                    @endif
                </div>

                <button
                    type="button"
                    class="sh-btn sh-btn--primary"
                    style="flex:1;"
                    wire:click="goToCheckout"
                    wire:loading.attr="disabled"
                    wire:target="goToCheckout"
                >
                    <span wire:loading.remove wire:target="goToCheckout">Continuar</span>
                    <span wire:loading wire:target="goToCheckout">Un momento…</span>
                    @include('shop.partials.icon', ['icon' => 'chevron'])
                </button>
            </div>
        </div>

        <template x-teleport=".sh-shell">
            <div>
                <div class="sh-backdrop sh-backdrop--confirm" x-show="emptyOpen" x-cloak x-transition.opacity.duration.200ms @click="emptyOpen = false"></div>
                <div
                    class="sh-sheet sh-sheet--confirm"
                    x-show="emptyOpen"
                    x-cloak
                    x-transition:enter="sh-sheet-anim"
                    x-transition:enter-start="sh-sheet-anim-start"
                    x-transition:enter-end="sh-sheet-anim-end"
                    x-transition:leave="sh-sheet-anim sh-sheet-anim-leave"
                    x-transition:leave-start="sh-sheet-anim-end"
                    x-transition:leave-end="sh-sheet-anim-start"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Vaciar carrito"
                >
                    <span class="sh-sheet__handle" style="margin:0.7rem auto 0.55rem;"></span>
                    <p class="sh-sheet__kicker">Carrito</p>
                    <h2 class="sh-h2" style="text-align:center;margin-bottom:0.85rem;">¿Vaciar todos los productos?</h2>
                    <div class="sh-sheet__actions">
                        <button type="button" class="sh-confirm-btn sh-confirm-btn--danger" @click="emptyOpen = false; $wire.confirmClear()">
                            @include('shop.partials.icon', ['icon' => 'trash'])
                            Vaciar carrito
                        </button>
                        <button type="button" class="sh-confirm-btn sh-confirm-btn--quiet" @click="emptyOpen = false">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
