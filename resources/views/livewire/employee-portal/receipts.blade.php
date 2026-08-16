<div class="ep-app ep-app--flow" x-data="employeePortalMenu">
    <header class="ep-topbar ep-glass ep-desktop-only">
        <div class="ep-brand ep-brand--bar">
            <img src="{{ asset('images/logos/favicon.png') }}" alt="Farmadoc">
            <span>Portal del empleado</span>
        </div>
        <div class="ep-topbar-actions">
            @include('employee-portal.partials.menu-button')
            <a href="{{ route('employee-portal.home') }}" class="ep-btn ep-btn--secondary ep-btn--compact" wire:navigate>Volver</a>
        </div>
    </header>

    <div class="ep-screen" wire:key="receipts">
        <div class="ep-nav ep-mobile-only">
            <a href="{{ route('employee-portal.home') }}" class="ep-ghost" wire:navigate>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                Atrás
            </a>
            <p class="ep-step">Recibos</p>
            @include('employee-portal.partials.menu-button')
        </div>

        <div class="ep-home-hero">
            <div>
                <p class="ep-hello">Tus pagos</p>
                <h1 class="ep-name">Recibos</h1>
                <p class="ep-text">Descarga el recibo mensual de nómina de ley. Solo aparecen meses ya cerrados, nunca meses futuros.</p>
            </div>
        </div>

        <p class="ep-section-label">Disponibles</p>
        <div class="ep-stack">
            @forelse ($receipts as $receipt)
                <a href="{{ route('employee-portal.receipts.download', $receipt) }}" class="ep-card-btn ep-glass">
                    <span class="ep-card-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </span>
                    <span class="ep-card-copy">
                        <strong>{{ $receipt->month_label }} {{ $receipt->year }}</strong>
                        <span>Recibo mensual · Bs {{ number_format((float) $receipt->total_ves, 2, ',', '.') }}</span>
                    </span>
                    <span class="ep-pill ep-pill--ok">PDF</span>
                </a>
            @empty
                <div class="ep-card-btn ep-glass" style="cursor: default;">
                    <span class="ep-card-copy">
                        <strong>Aún no hay recibos</strong>
                        <span>Cuando estén calculadas las dos quincenas del mes, o a partir del día 30, aquí podrás descargarlos.</span>
                    </span>
                </div>
            @endforelse
        </div>
    </div>

    @include('employee-portal.partials.menu-sheet', ['active' => 'receipts'])
</div>
