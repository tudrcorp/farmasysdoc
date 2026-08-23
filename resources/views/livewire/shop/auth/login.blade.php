<div class="sh-gate">
    <div class="sh-gate__pad">
        <header class="sh-gate__top">
            <a href="{{ route('shop.home') }}" wire:navigate class="sh-gate__back" aria-label="Volver">
                @include('shop.partials.icon', ['icon' => 'back'])
            </a>
            <div class="sh-gate__titles">
                <strong>Entrar</strong>
                <span>Tu cuenta</span>
            </div>
            <span aria-hidden="true"></span>
        </header>

        <div class="sh-gate__copy">
            <p class="sh-gate__eyebrow">Qué bueno verte de nuevo</p>
            <h1>Entrá a tu cuenta</h1>
            <p>Usá tu cédula, tu celular o el mismo Google con el que te registraste.</p>
        </div>

        @if ($authError)
            <p class="sh-gate__error" role="alert">{{ $authError }}</p>
        @endif

        <a href="{{ route('shop.google.redirect') }}" class="sh-btn sh-btn--google sh-btn--block">
            @include('shop.partials.google-logo')
            Continuar con Google
        </a>

        <p class="sh-gate__divider"><span>o entra con tus datos</span></p>

        <form class="sh-gate__form" wire:submit="authenticate">
            <div class="sh-gate__tabs" role="tablist" aria-label="Cómo entras" data-method="{{ $method }}">
                <button
                    type="button"
                    class="sh-gate__tab"
                    data-method="document"
                    @class(['is-on' => $method === 'document'])
                    wire:click="selectMethod('document')"
                    role="tab"
                    aria-selected="{{ $method === 'document' ? 'true' : 'false' }}"
                >
                    Cédula
                </button>
                <button
                    type="button"
                    class="sh-gate__tab"
                    data-method="phone"
                    @class(['is-on' => $method === 'phone'])
                    wire:click="selectMethod('phone')"
                    role="tab"
                    aria-selected="{{ $method === 'phone' ? 'true' : 'false' }}"
                >
                    Nro. de teléfono
                </button>
            </div>

            <label class="sh-gate__field">
                <span>{{ $method === 'document' ? 'Cédula de identidad' : 'Nro. de teléfono' }}</span>
                <span class="sh-gate__box">
                    <input
                        type="{{ $method === 'phone' ? 'tel' : 'text' }}"
                        inputmode="{{ $method === 'phone' ? 'tel' : 'numeric' }}"
                        size="1"
                        autocomplete="username"
                        wire:model="identifier"
                        placeholder="{{ $method === 'document' ? '12345678' : '0412-1234567' }}"
                    >
                </span>
                @error('identifier') <span class="sh-gate__error">{{ $message }}</span> @enderror
            </label>

            <label class="sh-gate__field">
                <span>Clave</span>
                <span class="sh-gate__box">
                    <input type="password" size="1" wire:model="password" autocomplete="current-password" placeholder="••••••••">
                </span>
                @error('password') <span class="sh-gate__error">{{ $message }}</span> @enderror
            </label>

            <button type="submit" class="sh-gate__submit" wire:loading.attr="disabled">
                Entrar
            </button>

            <p class="sh-gate__switch">
                ¿No tienes cuenta?
                <a href="{{ route('shop.register') }}" wire:navigate>Crear cuenta</a>
            </p>
        </form>
    </div>
</div>
