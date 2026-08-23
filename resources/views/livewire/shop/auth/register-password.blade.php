<div class="sh-gate">
    <div class="sh-gate__pad">
        <header class="sh-gate__top">
            <a href="{{ route('shop.register') }}" wire:navigate class="sh-gate__back" aria-label="Volver">
                @include('shop.partials.icon', ['icon' => 'back'])
            </a>
            <div class="sh-gate__titles">
                <strong>Crear cuenta</strong>
                <span>Tu clave</span>
            </div>
            <span class="sh-gate__step">2/2</span>
        </header>

        <div class="sh-gate__copy">
            <h1>Crea tu clave</h1>
            <p>La usaremos para cuidar tus pedidos y tus datos. Mínimo 4 caracteres.</p>
            <span class="sh-gate__chip">{{ $identityLabel }}</span>
        </div>

        <form class="sh-gate__form" wire:submit="register">
            <label class="sh-gate__field">
                <span>Clave</span>
                <span class="sh-gate__box">
                    <input type="password" size="1" wire:model="password" autocomplete="new-password" placeholder="••••••••">
                </span>
                @error('password') <span class="sh-gate__error">{{ $message }}</span> @enderror
            </label>

            <label class="sh-gate__field">
                <span>Confirmar clave</span>
                <span class="sh-gate__box">
                    <input type="password" size="1" wire:model="passwordConfirmation" autocomplete="new-password" placeholder="••••••••">
                </span>
                @error('passwordConfirmation') <span class="sh-gate__error">{{ $message }}</span> @enderror
            </label>

            <button type="submit" class="sh-gate__submit" wire:loading.attr="disabled">
                Crear mi cuenta
            </button>
        </form>
    </div>
</div>
