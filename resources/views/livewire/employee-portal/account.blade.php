<div class="ep-app ep-app--flow" x-data="employeePortalMenu">
    <header class="ep-topbar ep-glass ep-desktop-only">
        <div class="ep-brand ep-brand--bar">
            <img src="{{ asset('images/logos/favicon.png') }}" alt="Farmadoc">
            <span>Portal del empleado</span>
        </div>
        <div class="ep-topbar-actions">
            @include('employee-portal.partials.theme-toggle')
            @include('employee-portal.partials.menu-button')
            <a href="{{ route('employee-portal.home') }}" class="ep-btn ep-btn--secondary ep-btn--compact" wire:navigate>Volver</a>
        </div>
    </header>

    @if ($justCreated)
        <div class="ep-screen" wire:key="account-created">
            <div class="ep-success">
                <div>
                    <div class="ep-check" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor" class="size-10">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <h1 class="ep-lead">Clave creada</h1>
                    <p class="ep-text">Tu clave se creó de forma exitosa. La próxima vez que entres al portal te la pediremos después de tu cédula o teléfono.</p>
                    <div class="ep-actions">
                        <a href="{{ route('employee-portal.home') }}" class="ep-btn ep-btn--primary" wire:navigate>Ir al inicio</a>
                    </div>
                </div>
            </div>
        </div>
    @else
    <div class="ep-screen" wire:key="account">
        <div class="ep-nav ep-mobile-only">
            <a href="{{ route('employee-portal.home') }}" class="ep-ghost" wire:navigate>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                Inicio
            </a>
            <p class="ep-step">Cuenta</p>
            @include('employee-portal.partials.theme-toggle')
            @include('employee-portal.partials.menu-button')
        </div>

        <div class="ep-flow-intro">
            <h1 class="ep-lead">{{ $hasPassword ? 'Cambia tu clave' : 'Crea una clave' }}</h1>
            <p class="ep-text">
                @if ($hasPassword)
                    Tu portal ya está protegido. Puedes cambiar la clave o quitarla si prefieres entrar solo con cédula o teléfono.
                @else
                    Es opcional. Si la configuras, la próxima vez te la pediremos después de tu cédula o teléfono.
                @endif
            </p>

            @if ($saved)
                <p class="ep-toast">Listo, ya quedó guardado.</p>
            @endif

            <form class="ep-form" wire:submit="save">
                @if ($hasPassword)
                    <label class="ep-field">
                        <span>Clave actual</span>
                        <input type="password" autocomplete="current-password" wire:model="currentPassword">
                    </label>
                    @error('currentPassword') <p class="ep-error">{{ $message }}</p> @enderror
                @endif

                <label class="ep-field">
                    <span>{{ $hasPassword ? 'Nueva clave' : 'Clave' }}</span>
                    <input type="password" autocomplete="new-password" wire:model="password" placeholder="Mínimo 4 caracteres">
                </label>
                @error('password') <p class="ep-error">{{ $message }}</p> @enderror

                <label class="ep-field">
                    <span>Repite la clave</span>
                    <input type="password" autocomplete="new-password" wire:model="passwordConfirmation">
                </label>
                @error('passwordConfirmation') <p class="ep-error">{{ $message }}</p> @enderror

                <div class="ep-actions ep-actions--row">
                    <button type="submit" class="ep-btn ep-btn--primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $hasPassword ? 'Guardar clave' : 'Crear clave' }}</span>
                        <span wire:loading wire:target="save">Guardando…</span>
                    </button>
                    @if ($hasPassword)
                        <button type="button" class="ep-btn ep-btn--secondary" wire:click="remove" wire:loading.attr="disabled" wire:target="remove">
                            Quitar clave
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
    @endif

    @include('employee-portal.partials.menu-sheet', ['active' => 'account'])
</div>
