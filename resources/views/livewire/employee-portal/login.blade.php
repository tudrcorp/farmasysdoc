<div class="ep-app ep-app--flow">
    <div class="ep-screen ep-auth" wire:key="login-{{ $needsPassword ? 'password' : 'id' }}">
        <div class="ep-brand">
            <img src="{{ asset('images/logos/favicon.png') }}" alt="Farmadoc">
            <span>Portal</span>
            <div class="ep-header-actions">
                @include('employee-portal.partials.theme-toggle')
            </div>
        </div>

        <div class="ep-auth-body">
            @if (! $needsPassword)
                <h1 class="ep-lead">Hola, entra a tu portal</h1>
                <p class="ep-text">Usa tu cédula o tu número de teléfono. Si ya creaste una clave, te la pediremos en el siguiente paso.</p>

                <form class="ep-form" wire:submit="continue">
                    <label class="ep-field">
                        <span>Cédula o teléfono</span>
                        <input
                            type="text"
                            inputmode="tel"
                            autocomplete="username"
                            wire:model="identifier"
                            placeholder="V-12345678 o 0412…"
                        >
                    </label>
                    @error('identifier') <p class="ep-error">{{ $message }}</p> @enderror

                    <div class="ep-actions">
                        <button type="submit" class="ep-btn ep-btn--primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>Continuar</span>
                            <span wire:loading>Revisando…</span>
                        </button>
                    </div>
                </form>
            @else
                <button type="button" class="ep-ghost" wire:click="backToIdentifier" style="align-self: flex-start;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Atrás
                </button>
                <h1 class="ep-lead">Escribe tu clave</h1>
                <p class="ep-text">Esta cuenta está protegida. Entra con la clave que configuraste.</p>

                <form class="ep-form" wire:submit="authenticate">
                    <label class="ep-field">
                        <span>Clave</span>
                        <input
                            type="password"
                            autocomplete="current-password"
                            wire:model="password"
                            placeholder="Tu clave"
                        >
                    </label>
                    @error('password') <p class="ep-error">{{ $message }}</p> @enderror

                    <div class="ep-actions">
                        <button type="submit" class="ep-btn ep-btn--primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>Entrar</span>
                            <span wire:loading>Entrando…</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
