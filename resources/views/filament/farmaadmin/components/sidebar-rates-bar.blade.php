{{-- Barra del menú lateral (sobre «Escritorio»): tasa BCV en vivo + acceso a la conciliación BDV Pago Móvil --}}
<div class="farmadoc-sidebar-rates" aria-label="Tasa BCV y conciliación BDV">
    @livewire('filament.bcv-exchange-rate-badge', [], key('farmadoc-bcv-rate-badge'))

    @if ($showBdv)
        @livewire('filament.bdv-pagomovil-conciliation-fab', [], key('farmadoc-bdv-pm-fab'))
    @endif
</div>
