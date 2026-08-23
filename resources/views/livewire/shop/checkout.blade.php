@php
    $stepTitles = [
        1 => 'Entrega',
        2 => 'Tus datos',
        3 => 'Pago',
    ];
    $selectedBranch = $branches->firstWhere('id', $branchId);
@endphp

<div class="sh-checkout">
    @include('shop.partials.header', [
        'title' => $stepTitles[$step] ?? 'Checkout',
        'back' => route('shop.cart'),
        'showCart' => false,
    ])

    <main class="sh-main sh-main--action">
        <div class="sh-page" style="padding-top:0.9rem;">
            {{-- Progreso --}}
            <div class="sh-steps" aria-label="Paso {{ $step }} de 3">
                @foreach ([1, 2, 3] as $index)
                    <span @class([
                        'sh-steps__item',
                        'is-done' => $index < $step,
                        'is-current' => $index === $step,
                    ])></span>
                @endforeach
            </div>

            <p class="sh-eyebrow" style="margin-top:0.9rem;">Paso {{ $step }} de 3</p>

            {{-- ---------- Paso 1: entrega ---------- --}}
            @if ($step === 1)
                <h2 class="sh-h1" style="font-size:1.4rem;">¿Cómo lo recibes?</h2>

                <div class="sh-form" style="margin-top:1.1rem;">
                    <button
                        type="button"
                        @class(['sh-option', 'is-selected' => ! $this->isPickup()])
                        wire:click="selectFulfillment('delivery')"
                    >
                        <span class="sh-option__icon" aria-hidden="true">
                            @include('shop.partials.icon', ['icon' => 'truck'])
                        </span>
                        <span class="sh-option__copy">
                            <strong>Entrega a domicilio</strong>
                            <span>Te lo llevamos hoy mismo</span>
                        </span>
                        <span class="sh-radio" aria-hidden="true"></span>
                    </button>

                    <button
                        type="button"
                        @class(['sh-option', 'is-selected' => $this->isPickup()])
                        wire:click="selectFulfillment('pickup')"
                    >
                        <span class="sh-option__icon" aria-hidden="true">
                            @include('shop.partials.icon', ['icon' => 'store'])
                        </span>
                        <span class="sh-option__copy">
                            <strong>Retiro en sucursal</strong>
                            <span>Sin costo de envío</span>
                        </span>
                        <span class="sh-radio" aria-hidden="true"></span>
                    </button>
                </div>

                @if ($this->isPickup())
                    <p class="sh-sheet__label">Elige tu sucursal</p>

                    <div class="sh-form">
                        @foreach ($branches as $branch)
                            <button
                                type="button"
                                @class(['sh-option', 'is-selected' => $branchId === $branch->id])
                                wire:click="selectBranch({{ $branch->id }})"
                                wire:key="branch-{{ $branch->id }}"
                            >
                                <span class="sh-option__icon" aria-hidden="true">
                                    @include('shop.partials.icon', ['icon' => 'pin'])
                                </span>
                                <span class="sh-option__copy">
                                    <strong>{{ $branch->name }}</strong>
                                    <span>{{ $branch->address ?: $branch->city }}</span>
                                </span>
                                <span class="sh-radio" aria-hidden="true"></span>
                            </button>
                        @endforeach
                    </div>

                    @error('branchId')
                        <p class="sh-error" style="margin-top:0.5rem;">
                            @include('shop.partials.icon', ['icon' => 'alert'])
                            {{ $message }}
                        </p>
                    @enderror
                @else
                    <div class="sh-form" style="margin-top:1.1rem;">
                        <div class="sh-field">
                            <label class="sh-field__label" for="ck-address">Dirección de entrega</label>
                            <textarea
                                id="ck-address"
                                wire:model.blur="address"
                                @class(['sh-textarea', 'is-invalid' => $errors->has('address')])
                                placeholder="Calle, edificio o casa, punto de referencia"
                                rows="3"
                            ></textarea>
                            @error('address')
                                <p class="sh-error">
                                    @include('shop.partials.icon', ['icon' => 'alert'])
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="sh-form__split">
                            <div class="sh-field">
                                <label class="sh-field__label" for="ck-city">Ciudad</label>
                                <input
                                    id="ck-city"
                                    type="text"
                                    size="1"
                                    wire:model.blur="city"
                                    @class(['sh-input', 'is-invalid' => $errors->has('city')])
                                    placeholder="Barinas"
                                    autocomplete="address-level2"
                                >
                            </div>

                            <div class="sh-field">
                                <label class="sh-field__label" for="ck-state">Estado</label>
                                <input
                                    id="ck-state"
                                    type="text"
                                    size="1"
                                    wire:model.blur="state"
                                    @class(['sh-input', 'is-invalid' => $errors->has('state')])
                                    placeholder="Barinas"
                                    autocomplete="address-level1"
                                >
                            </div>
                        </div>

                        @error('city')
                            <p class="sh-error">
                                @include('shop.partials.icon', ['icon' => 'alert'])
                                {{ $message }}
                            </p>
                        @enderror
                        @error('state')
                            <p class="sh-error">
                                @include('shop.partials.icon', ['icon' => 'alert'])
                                {{ $message }}
                            </p>
                        @enderror

                        <div class="sh-field">
                            <label class="sh-field__label" for="ck-delivery-notes">Referencia (opcional)</label>
                            <input
                                id="ck-delivery-notes"
                                type="text"
                                size="1"
                                wire:model.blur="deliveryNotes"
                                class="sh-input"
                                placeholder="Piso, apartamento, horario"
                            >
                        </div>
                    </div>
                @endif
            @endif

            {{-- ---------- Paso 2: datos ---------- --}}
            @if ($step === 2)
                <h2 class="sh-h1" style="font-size:1.4rem;">¿Quién recibe?</h2>
                <p class="sh-sub">Usamos estos datos para tu factura y para avisarte del pedido.</p>

                <div class="sh-form" style="margin-top:1.1rem;">
                    <div class="sh-field">
                        <label class="sh-field__label" for="ck-name">Nombre completo</label>
                        <input
                            id="ck-name"
                            type="text"
                            size="1"
                            wire:model.blur="name"
                            @class(['sh-input', 'is-invalid' => $errors->has('name')])
                            placeholder="María Pérez"
                            autocomplete="name"
                        >
                        @error('name')
                            <p class="sh-error">
                                @include('shop.partials.icon', ['icon' => 'alert'])
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="sh-field">
                        <label class="sh-field__label" for="ck-document">Documento</label>
                        <div class="sh-form__row">
                            <select id="ck-document-type" wire:model.blur="documentType" class="sh-select" aria-label="Tipo de documento">
                                <option value="CC">C.I.</option>
                                <option value="CE">C.E.</option>
                                <option value="RIF">RIF</option>
                                <option value="PAS">Pasaporte</option>
                            </select>
                            <input
                                id="ck-document"
                                type="text"
                                size="1"
                                wire:model.blur="documentNumber"
                                @class(['sh-input', 'is-invalid' => $errors->has('documentNumber')])
                                placeholder="12345678"
                                inputmode="numeric"
                            >
                        </div>
                        @error('documentNumber')
                            <p class="sh-error">
                                @include('shop.partials.icon', ['icon' => 'alert'])
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="sh-field">
                        <label class="sh-field__label" for="ck-phone">Teléfono</label>
                        <input
                            id="ck-phone"
                            type="tel"
                            size="1"
                            wire:model.blur="phone"
                            @class(['sh-input', 'is-invalid' => $errors->has('phone')])
                            placeholder="0412 701 8390"
                            inputmode="tel"
                            autocomplete="tel"
                        >
                        @error('phone')
                            <p class="sh-error">
                                @include('shop.partials.icon', ['icon' => 'alert'])
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="sh-field">
                        <label class="sh-field__label" for="ck-email">Correo</label>
                        <input
                            id="ck-email"
                            type="email"
                            size="1"
                            wire:model.blur="email"
                            @class(['sh-input', 'is-invalid' => $errors->has('email')])
                            placeholder="maria@correo.com"
                            inputmode="email"
                            autocomplete="email"
                        >
                        @error('email')
                            <p class="sh-error">
                                @include('shop.partials.icon', ['icon' => 'alert'])
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>
            @endif

            {{-- ---------- Paso 3: pago y revisión ---------- --}}
            @if ($step === 3)
                <h2 class="sh-h1" style="font-size:1.4rem;">¿Cómo pagas?</h2>

                <div class="sh-form" style="margin-top:1.1rem;">
                    @foreach ($paymentMethods as $key => $method)
                        <button
                            type="button"
                            @class(['sh-option', 'is-selected' => $paymentMethod === $key])
                            wire:click="selectPaymentMethod('{{ $key }}')"
                            wire:key="pay-{{ $key }}"
                        >
                            <span class="sh-option__icon" aria-hidden="true">
                                @include('shop.partials.icon', ['icon' => match ($key) {
                                    'pago_movil' => 'phone',
                                    'efectivo' => 'cash',
                                    'transferencia' => 'bank',
                                    default => 'card',
                                }])
                            </span>
                            <span class="sh-option__copy">
                                <strong>{{ $method['label'] }}</strong>
                                <span>{{ $method['hint'] }}</span>
                            </span>
                            <span class="sh-radio" aria-hidden="true"></span>
                        </button>
                    @endforeach
                </div>

                <div class="sh-field" style="margin-top:1rem;">
                    <label class="sh-field__label" for="ck-notes">Nota para la farmacia (opcional)</label>
                    <textarea
                        id="ck-notes"
                        wire:model.blur="notes"
                        class="sh-textarea"
                        rows="2"
                        placeholder="Ej. necesito la factura a nombre de…"
                    ></textarea>
                </div>

                {{-- Revisión --}}
                <p class="sh-sheet__label">Revisa tu pedido</p>

                <div class="sh-summary">
                    <div class="sh-summary__row">
                        <span>{{ $this->isPickup() ? 'Retiras en' : 'Enviamos a' }}</span>
                        <strong style="text-align:right;max-width:62%;font-size:0.82rem;">
                            {{ $this->isPickup() ? ($selectedBranch?->name ?? 'Sucursal') : \Illuminate\Support\Str::limit($address, 46) }}
                        </strong>
                    </div>
                    <div class="sh-summary__row">
                        <span>Recibe</span>
                        <strong style="font-size:0.82rem;">{{ $name }}</strong>
                    </div>
                    <div class="sh-summary__row">
                        <span>Pago</span>
                        <strong style="font-size:0.82rem;">{{ $paymentMethods[$paymentMethod]['label'] ?? $paymentMethod }}</strong>
                    </div>

                    <div class="sh-summary__rule"></div>

                    <div class="sh-summary__row">
                        <span>{{ $totals['units'] }} {{ \Illuminate\Support\Str::plural('unidad', $totals['units']) }}</span>
                        <strong>${{ number_format($totals['net'], 2) }}</strong>
                    </div>

                    @if ($totals['discount'] > 0)
                        <div class="sh-summary__row sh-summary__row--discount">
                            <span>Descuentos</span>
                            <strong>-${{ number_format($totals['discount'], 2) }}</strong>
                        </div>
                    @endif

                    @if ($totals['tax'] > 0)
                        <div class="sh-summary__row">
                            <span>IVA</span>
                            <strong>${{ number_format($totals['tax'], 2) }}</strong>
                        </div>
                    @endif

                    <div class="sh-summary__rule"></div>

                    <div class="sh-summary__row sh-summary__row--total">
                        <span>Total</span>
                        <strong>${{ number_format($totals['total'], 2) }}</strong>
                    </div>
                </div>

                <div class="sh-note sh-note--info" style="margin-top:1rem;">
                    @include('shop.partials.icon', ['icon' => 'info'])
                    <span>Al confirmar creamos tu pedido y te contactamos para coordinar el pago y la entrega.</span>
                </div>
            @endif
        </div>
    </main>

    {{-- Acción única de la pantalla --}}
    <div class="sh-actionbar">
        <div class="sh-actionbar__row">
            <button
                type="button"
                class="sh-btn sh-btn--ghost"
                style="min-width:3.2rem;padding-inline:0.85rem;"
                wire:click="previousStep"
                aria-label="Paso anterior"
            >
                @include('shop.partials.icon', ['icon' => 'back'])
            </button>

            @if ($step < 3)
                <button
                    type="button"
                    class="sh-btn sh-btn--primary"
                    style="flex:1;"
                    wire:click="nextStep"
                    wire:loading.attr="disabled"
                    wire:target="nextStep"
                >
                    Continuar
                    @include('shop.partials.icon', ['icon' => 'chevron'])
                </button>
            @else
                <button
                    type="button"
                    class="sh-btn sh-btn--primary"
                    style="flex:1;"
                    wire:click="placeOrder"
                    wire:loading.attr="disabled"
                    wire:target="placeOrder"
                    @disabled($placing)
                >
                    <span wire:loading.remove wire:target="placeOrder">
                        Confirmar · ${{ number_format($totals['total'], 2) }}
                    </span>
                    <span wire:loading wire:target="placeOrder">Creando tu pedido…</span>
                </button>
            @endif
        </div>
    </div>
</div>
