<div class="ep-app" x-data="employeePortalMenu">
    <header class="ep-topbar ep-glass ep-desktop-only">
        <div class="ep-brand ep-brand--bar">
            <img src="{{ asset('images/logos/favicon.png') }}" alt="Farmadoc">
            <span>Portal del empleado</span>
        </div>
        <div class="ep-topbar-actions">
            @include('employee-portal.partials.theme-toggle')
            @include('employee-portal.partials.menu-button')
            <a href="{{ route('employee-portal.logout') }}" class="ep-btn ep-btn--secondary ep-btn--compact">Salir</a>
        </div>
    </header>

    <div class="ep-screen" wire:key="home">
        <div class="ep-brand ep-mobile-only">
            <img src="{{ asset('images/logos/favicon.png') }}" alt="Farmadoc">
            <span>Portal</span>
            <div class="ep-header-actions">
                @include('employee-portal.partials.theme-toggle')
                @include('employee-portal.partials.menu-button')
            </div>
        </div>

        <div class="ep-home-hero">
            <div>
                <p class="ep-hello">{{ $greeting }}</p>
                <h1 class="ep-name">{{ $employee->first_name }}</h1>
                <p class="ep-text ep-desktop-only">Desde aquí completas tu expediente y, más adelante, vas a pedir constancias, vacaciones y permisos.</p>
            </div>
            <div class="ep-profile ep-glass">
                <div class="ep-avatar">
                    @if ($employee->hasPhoto())
                        <img src="{{ $employee->photoUrl() }}" alt="">
                    @else
                        {{ $employee->initials() }}
                    @endif
                </div>
                <div>
                    <h2>{{ $employee->fullName() }}</h2>
                    <p>C.I. {{ $employee->national_id }} · {{ $employee->branch?->name }}</p>
                </div>
            </div>
        </div>

        <p class="ep-section-label">Tu expediente</p>
        <div class="ep-stack">
        <a href="{{ route('employee-portal.file') }}" class="ep-card-btn ep-card-btn--featured ep-glass" wire:navigate>
            <span class="ep-card-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25 4.5 21m0 0 3.375-3.375M4.5 21l3.375-3.375m9.75-9.75a4.125 4.125 0 1 1-8.25 0 4.125 4.125 0 0 1 8.25 0ZM16.5 21a4.125 4.125 0 1 0 0-8.25 4.125 4.125 0 0 0 0 8.25Z" />
                </svg>
            </span>
            <span class="ep-card-copy">
                <strong>Firma y huella</strong>
                <span>
                    @if ($fileComplete)
                        Ya están en tu expediente. Puedes actualizarlas cuando quieras.
                    @else
                        Fírmalo con el dedo y toma la foto de tu huella. Toma menos de un minuto.
                    @endif
                </span>
            </span>
            <span @class(['ep-pill', 'ep-pill--ok' => $fileComplete, 'ep-pill--warn' => ! $fileComplete])>
                {{ $fileComplete ? 'Listo' : 'Pendiente' }}
            </span>
        </a>

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

        <a href="{{ route('employee-portal.account') }}" class="ep-card-btn ep-glass" wire:navigate>
            <span class="ep-card-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
            </span>
            <span class="ep-card-copy">
                <strong>Tu clave</strong>
                <span>
                    @if ($employee->hasPortalPassword())
                        Ya protegiste tu portal. Puedes cambiarla cuando quieras.
                    @else
                        Opcional. Si la creas, nadie entra solo con tu cédula o teléfono.
                    @endif
                </span>
            </span>
            <span @class(['ep-pill', 'ep-pill--ok' => $employee->hasPortalPassword(), 'ep-pill--warn' => ! $employee->hasPortalPassword()])>
                {{ $employee->hasPortalPassword() ? 'Activa' : 'Sin clave' }}
            </span>
        </a>
        </div>

        <p class="ep-section-label">Próximamente</p>
        <div class="ep-stack ep-services">
            <button type="button" class="ep-card-btn ep-glass" wire:click="openComingSoon">
                <span class="ep-card-icon ep-card-icon--muted" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </span>
                <span class="ep-card-copy">
                    <strong>Constancia de trabajo</strong>
                    <span>Solicítala y descárgala cuando la necesites.</span>
                </span>
                <span class="ep-pill ep-pill--soon">Pronto</span>
            </button>
            <button type="button" class="ep-card-btn ep-glass" wire:click="openComingSoon">
                <span class="ep-card-icon ep-card-icon--muted" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </span>
                <span class="ep-card-copy">
                    <strong>Vacaciones</strong>
                    <span>Pide tus días y sigue el estado de la solicitud.</span>
                </span>
                <span class="ep-pill ep-pill--soon">Pronto</span>
            </button>
            <button type="button" class="ep-card-btn ep-glass" wire:click="openComingSoon">
                <span class="ep-card-icon ep-card-icon--muted" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>
                <span class="ep-card-copy">
                    <strong>Permiso de ausencia</strong>
                    <span>Justifica una ausencia y adjunta el soporte.</span>
                </span>
                <span class="ep-pill ep-pill--soon">Pronto</span>
            </button>
        </div>
    </div>

    @include('employee-portal.partials.menu-sheet', ['active' => 'home'])

    @if ($showComingSoon)
        <div class="ep-sheet-backdrop" wire:click="closeComingSoon" wire:key="soon-backdrop"></div>
        <div class="ep-sheet ep-glass" wire:key="soon-sheet" role="dialog" aria-modal="true" aria-labelledby="ep-soon-title">
            <div class="ep-sheet-handle"></div>
            <h2 id="ep-soon-title" class="ep-lead" style="font-size: 1.45rem;">Muy pronto aquí</h2>
            <p class="ep-text">Desde este mismo portal vas a poder pedir constancia de trabajo, vacaciones y permisos de ausencia. Hoy solo necesitamos tu firma y tu huella.</p>
            <div class="ep-actions">
                <button type="button" class="ep-btn ep-btn--primary" wire:click="closeComingSoon">Entendido</button>
                <button type="button" class="ep-btn ep-btn--secondary" wire:click="leave">Cerrar sesión</button>
            </div>
        </div>
    @endif
</div>
