@php
    $selectedBranch = $branches->firstWhere('id', $branchId);
    $paymentLabel = $paymentMethods[$paymentMethod]['label'] ?? 'Pago';
    $fulfillmentLabel = $this->isPickup() ? 'Retiro en sucursal' : 'Delivery a domicilio';
@endphp

<div class="fd-pay">
    <header class="fd-pay__top">
        <a href="{{ route('home') }}" class="fd-logo" aria-label="Volver a Farmadoc">
            <img src="{{ asset('images/logos/farmadoc-ligth.png') }}" alt="Farmadoc" class="dark:hidden">
            <img src="{{ asset('images/logos/farmadoc-dark.png') }}" alt="Farmadoc" class="hidden dark:block">
        </a>

        <p class="fd-pay__secure" role="status">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M7 11V8a5 5 0 0 1 10 0v3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <rect x="5" y="11" width="14" height="10" rx="2.4" fill="currentColor" opacity=".14" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="16" r="1.4" fill="currentColor"/>
            </svg>
            <span>Zona segura</span>
            <em>Conexión cifrada · Farmadoc Pay</em>
        </p>
    </header>

    <main class="fd-pay__grid">
        <aside class="fd-pay__summary fd-glass" aria-label="Resumen del pedido">
            <p class="fd-kicker">Tu pedido</p>
            <h2>Resumen</h2>

            <ul class="fd-pay__lines">
                @foreach ($lines as $line)
                    <li wire:key="line-{{ $line['product_id'] }}">
                        <img src="{{ $line['product']['image_url'] }}" alt="">
                        <div>
                            <strong>{{ $line['product']['name'] }}</strong>
                            <small>x{{ (int) $line['quantity'] }} · {{ $line['product']['brand'] }}</small>
                        </div>
                        <span>${{ number_format((float) $line['line_total'], 2) }}</span>
                    </li>
                @endforeach
            </ul>

            <dl class="fd-pay__totals">
                <div>
                    <dt>Productos</dt>
                    <dd>{{ $totals['units'] }}</dd>
                </div>
                <div>
                    <dt>Total USD</dt>
                    <dd>${{ number_format((float) $totals['total'], 2) }}</dd>
                </div>
                <div class="fd-pay__totals-ves">
                    <dt>Total VES</dt>
                    <dd>
                        @if ($vesTotal !== null)
                            Bs. {{ number_format($vesTotal, 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($usdVesRate)
                <p class="fd-pay__rate">Tasa BCV · Bs. {{ number_format($usdVesRate, 2, ',', '.') }} / USD</p>
            @endif

            <p class="fd-pay__badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 11V8a5 5 0 0 1 10 0v3"/><rect x="5" y="11" width="14" height="10" rx="2"/></svg>
                Pagos procesados en un entorno seguro de Farmadoc.
            </p>
        </aside>

        <section class="fd-pay__panel" aria-labelledby="fd-pay-title">
            <p class="fd-kicker">Pasarela de pago</p>
            <h1 id="fd-pay-title">Completa tu pago con seguridad</h1>
            <p class="fd-pay__lead">
                Estás en una zona protegida. Tus datos de envío y el método de pago quedan cifrados en tránsito. Farmadoc nunca te pedirá claves bancarias por WhatsApp.
            </p>

            <div class="fd-pay__trust" aria-label="Garantías de seguridad">
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 12 5 5L20 7"/></svg>
                    Cifrado TLS
                </span>
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 3 5 6v6c0 5 3.2 8.4 7 9 3.8-.6 7-4 7-9V6l-7-3z"/></svg>
                    Pago verificado
                </span>
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.4 1.4"/></svg>
                    Confirmación inmediata
                </span>
            </div>

            <h2>¿Cómo lo recibes?</h2>
            <div class="fd-pay__choices" role="radiogroup" aria-label="Método de envío o retiro">
                <button
                    type="button"
                    @class(['fd-pay__choice', 'is-on' => ! $this->isPickup()])
                    wire:click="selectFulfillment('delivery')"
                >
                    <span class="fd-pay__choice-icon" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none">
                            <rect x="2.5" y="12" width="16.5" height="10.5" rx="2.2" fill="#0E949A"/>
                            <path d="M19 14.5h5.2L27.5 19v3.5H19v-8z" fill="#18ACB2"/>
                            <rect x="6.2" y="7.2" width="8.2" height="6.2" rx="1.3" fill="#FCE422"/>
                            <circle cx="9.2" cy="23.8" r="2.35" fill="#10282C"/>
                            <circle cx="23.4" cy="23.8" r="2.35" fill="#10282C"/>
                        </svg>
                    </span>
                    <span>
                        <strong>Delivery</strong>
                        <small>Te lo llevamos a domicilio</small>
                    </span>
                </button>

                <button
                    type="button"
                    @class(['fd-pay__choice', 'is-on' => $this->isPickup()])
                    wire:click="selectFulfillment('pickup')"
                >
                    <span class="fd-pay__choice-icon" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none">
                            <path d="M5 14 16 6l11 8v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V14z" fill="#0E949A"/>
                            <rect x="12" y="18" width="8" height="10" rx="1.2" fill="#FCE422"/>
                            <path d="M9 14.5h14" stroke="#18ACB2" stroke-width="1.6"/>
                        </svg>
                    </span>
                    <span>
                        <strong>Retiro en sucursal</strong>
                        <small>Sin costo de envío</small>
                    </span>
                </button>
            </div>

            @if ($this->isPickup())
                <p class="fd-pay__label">Sucursal de retiro</p>
                <div class="fd-pay__branches">
                    @foreach ($branches as $branch)
                        <button
                            type="button"
                            @class(['fd-pay__branch', 'is-on' => $branchId === $branch->id])
                            wire:click="selectBranch({{ $branch->id }})"
                            wire:key="branch-{{ $branch->id }}"
                        >
                            <strong>{{ $branch->name }}</strong>
                            <small>{{ $branch->address ?: trim(($branch->city ?? '').' '.($branch->state ?? '')) }}</small>
                        </button>
                    @endforeach
                </div>
                @error('branchId')
                    <p class="fd-pay__error">{{ $message }}</p>
                @enderror
            @else
                <div class="fd-pay__fields">
                    <label class="fd-pay__field">
                        <span>Dirección de entrega</span>
                        <textarea wire:model.blur="address" rows="3" placeholder="Calle, edificio o casa, punto de referencia"></textarea>
                        @error('address') <em>{{ $message }}</em> @enderror
                    </label>
                    <div class="fd-pay__split">
                        <label class="fd-pay__field">
                            <span>Ciudad</span>
                            <input type="text" wire:model.blur="city" placeholder="Caracas">
                            @error('city') <em>{{ $message }}</em> @enderror
                        </label>
                        <label class="fd-pay__field">
                            <span>Estado</span>
                            <input type="text" wire:model.blur="state" placeholder="Distrito Capital">
                            @error('state') <em>{{ $message }}</em> @enderror
                        </label>
                    </div>
                    <label class="fd-pay__field">
                        <span>Indicaciones (opcional)</span>
                        <input type="text" wire:model.blur="deliveryNotes" placeholder="Portería, horario, referencia">
                    </label>
                </div>
            @endif

            <h2>Método de pago</h2>
            <div class="fd-pay__choices" role="radiogroup" aria-label="Método de pago">
                @foreach ($paymentMethods as $key => $method)
                    <button
                        type="button"
                        @class(['fd-pay__choice', 'is-on' => $paymentMethod === $key])
                        wire:click="selectPaymentMethod('{{ $key }}')"
                        wire:key="pay-{{ $key }}"
                    >
                        <span class="fd-pay__choice-icon" aria-hidden="true">
                            @if ($key === 'pago_movil')
                                <svg viewBox="0 0 32 32" fill="none">
                                    <rect x="8" y="4" width="16" height="24" rx="3.2" fill="#0E949A"/>
                                    <rect x="11" y="8" width="10" height="12" rx="1.4" fill="#E7F7F8"/>
                                    <circle cx="16" cy="24.2" r="1.4" fill="#FCE422"/>
                                </svg>
                            @else
                                <svg viewBox="0 0 32 32" fill="none">
                                    <rect x="4" y="8" width="24" height="16" rx="3" fill="#0E949A"/>
                                    <rect x="4" y="12" width="24" height="3.4" fill="#18ACB2"/>
                                    <rect x="7.2" y="18.4" width="8" height="2.2" rx="1" fill="#FCE422"/>
                                </svg>
                            @endif
                        </span>
                        <span>
                            <strong>{{ $method['label'] }}</strong>
                            <small>{{ $method['hint'] }}</small>
                        </span>
                    </button>
                @endforeach
            </div>
            @error('paymentMethod')
                <p class="fd-pay__error">{{ $message }}</p>
            @enderror

            @if ($ready)
                <div class="fd-pay__ready" role="status">
                    <strong>Envío y pago listos</strong>
                    <p>{{ $fulfillmentLabel }} · {{ $paymentLabel }}. El siguiente paso serán tus datos para confirmar el pedido.</p>
                </div>
            @endif

            <button
                type="button"
                class="fd-btn fd-btn--primary fd-pay__cta"
                wire:click="continueSecurely"
                wire:loading.attr="disabled"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path d="M7 11V8a5 5 0 0 1 10 0v3"/>
                    <rect x="5" y="11" width="14" height="10" rx="2.2"/>
                </svg>
                <span wire:loading.remove>Continuar de forma segura</span>
                <span wire:loading>Validando…</span>
            </button>

            <p class="fd-pay__fine">
                Al continuar aceptas pagar el total indicado. Farmadoc solo usa estos datos para preparar tu pedido.
            </p>
        </section>
    </main>
</div>
