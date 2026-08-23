<div class="sh-auth sh-auth--welcome">
    <img
        src="{{ asset('images/logos/farmadoc-ligth.png') }}"
        alt="Farmadoc"
        class="sh-auth__logo sh-auth__logo--hero sh-auth__logo--light"
        width="400"
        height="128"
    >
    <img
        src="{{ asset('images/logos/farmadoc-dark.png') }}"
        alt="Farmadoc"
        class="sh-auth__logo sh-auth__logo--hero sh-auth__logo--dark"
        width="400"
        height="128"
    >

    <p class="sh-auth__hello">Bienvenido a tu farmacia</p>

    @if ($authError)
        <p class="sh-error" role="alert">{{ $authError }}</p>
    @endif

    <div class="sh-auth__actions">
        <a href="{{ route('shop.google.redirect') }}" class="sh-btn sh-btn--google sh-btn--block">
            @include('shop.partials.google-logo')
            Continuar con Google
        </a>

        <a href="{{ route('shop.register') }}" wire:navigate class="sh-btn sh-btn--primary sh-btn--block">
            Crear cuenta
        </a>

        <p class="sh-auth__switch">
            ¿Ya tienes cuenta?
            <a href="{{ route('shop.login') }}" wire:navigate>Entrar</a>
        </p>
    </div>
</div>
