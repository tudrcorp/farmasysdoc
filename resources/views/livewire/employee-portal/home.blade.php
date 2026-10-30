<div class="ep-app" x-data="employeePortalMenu">
    <header class="ep-topbar ep-glass ep-desktop-only">
        <div class="ep-brand ep-brand--bar">
            <img src="{{ asset('images/logos/favicon.png') }}" alt="Farmadoc">
            <span>Portal del empleado</span>
        </div>
        <div class="ep-topbar-actions">
            @include('employee-portal.partials.menu-button')
            <a href="{{ route('employee-portal.logout') }}" class="ep-btn ep-btn--secondary ep-btn--compact">Salir</a>
        </div>
    </header>

    <div class="ep-screen" wire:key="home">
        <div class="ep-brand ep-mobile-only">
            <img src="{{ asset('images/logos/favicon.png') }}" alt="Farmadoc">
            <span>Portal</span>
            <div class="ep-header-actions">
                @include('employee-portal.partials.menu-button')
            </div>
        </div>

        <div class="ep-home-hero">
            <div>
                <p class="ep-hello">{{ $greeting }}</p>
                <h1 class="ep-name">{{ $employee->first_name }}</h1>
                <p class="ep-text ep-desktop-only">Desde aquí completas tu expediente y pides constancias, vacaciones, permisos y reposos.</p>
            </div>
        </div>

        <p class="ep-section-label">Tu expediente</p>
        <div class="ep-stack">
        @unless ($fileComplete)
        <a href="{{ route('employee-portal.file') }}" class="ep-card-btn ep-card-btn--featured ep-glass" wire:navigate>
            <span class="ep-card-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25 4.5 21m0 0 3.375-3.375M4.5 21l3.375-3.375m9.75-9.75a4.125 4.125 0 1 1-8.25 0 4.125 4.125 0 0 1 8.25 0ZM16.5 21a4.125 4.125 0 1 0 0-8.25 4.125 4.125 0 0 0 0 8.25Z" />
                </svg>
            </span>
            <span class="ep-card-copy">
                <strong>Firma y huella</strong>
                <span>Fírmalo con el dedo y toma la foto de tu huella. Toma menos de un minuto.</span>
            </span>
            <span class="ep-pill ep-pill--warn">Pendiente</span>
        </a>
        @endunless

        <a href="{{ route('employee-portal.receipts') }}" class="ep-card-btn ep-glass" wire:navigate>
            <span class="ep-card-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </span>
            <span class="ep-card-copy">
                <strong>Recibos de nómina</strong>
                <span>Descarga el recibo mensual de ley, con tu firma y huella.</span>
            </span>
            <span class="ep-pill">PDF</span>
        </a>

        <a href="{{ route('employee-portal.trainings') }}" class="ep-card-btn ep-glass" wire:navigate>
            <span class="ep-card-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342" />
                </svg>
            </span>
            <span class="ep-card-copy">
                <strong>Capacitaciones</strong>
                <span>Tus próximas fechas, historial y certificados.</span>
            </span>
        </a>

        @unless ($hasPortalPassword)
        <a href="{{ route('employee-portal.account') }}" class="ep-card-btn ep-glass" wire:navigate>
            <span class="ep-card-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
            </span>
            <span class="ep-card-copy">
                <strong>Tu clave</strong>
                <span>Opcional. Si la creas, nadie entra solo con tu cédula o teléfono.</span>
            </span>
            <span class="ep-pill ep-pill--warn">Sin clave</span>
        </a>
        @endunless
        </div>

        <div class="ep-section-head">
            <p class="ep-section-label">Solicitudes</p>
            <a href="{{ route('employee-portal.requests') }}" class="ep-section-link" wire:navigate>
                Ver mis solicitudes
                @if ($pendingRequestsCount > 0)
                    <span class="ep-pill ep-pill--warn">{{ $pendingRequestsCount }} por aprobar</span>
                @endif
            </a>
        </div>
        <div class="ep-stack ep-services">
            @foreach ($requestCards as $card)
                <a href="{{ $card['href'] }}" class="ep-card-btn ep-glass" wire:navigate>
                    <span class="ep-card-icon ep-card-icon--{{ $card['tone'] }}" aria-hidden="true">
                        @include('employee-portal.partials.menu-icon', ['icon' => $card['icon']])
                    </span>
                    <span class="ep-card-copy">
                        <strong>{{ $card['title'] }}</strong>
                        <span>{{ $card['subtitle'] }}</span>
                    </span>
                    <span class="ep-pill">Pedir</span>
                </a>
            @endforeach
        </div>
    </div>

    @include('employee-portal.partials.menu-sheet', ['active' => 'home'])

</div>
