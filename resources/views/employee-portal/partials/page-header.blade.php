{{-- Encabezado común de las pantallas internas del portal: barra de escritorio + navegación móvil. --}}
<header class="ep-topbar ep-glass ep-desktop-only">
    <div class="ep-brand ep-brand--bar">
        <img src="{{ asset('images/logos/favicon.png') }}" alt="Farmadoc">
        <span>Portal del empleado</span>
    </div>
    <div class="ep-topbar-actions">
        @include('employee-portal.partials.menu-button')
        <a href="{{ $backHref }}" class="ep-btn ep-btn--secondary ep-btn--compact" wire:navigate>Volver</a>
    </div>
</header>
