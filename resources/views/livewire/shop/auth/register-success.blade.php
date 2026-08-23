<div class="sh-auth sh-auth--center">
    <span class="sh-auth__success" aria-hidden="true">
        @include('shop.partials.icon', ['icon' => 'check'])
    </span>

    <h1 class="sh-auth__title">¡Listo, {{ $firstName }}!</h1>
    <p class="sh-auth__lead">Tu cuenta ya está activa. Desde ahora pedís, das seguimiento y recompras en un toque.</p>

    <button type="button" class="sh-btn sh-btn--yellow sh-btn--block sh-auth__enter" wire:click="enter">
        Entrar a la aplicación
    </button>
</div>
