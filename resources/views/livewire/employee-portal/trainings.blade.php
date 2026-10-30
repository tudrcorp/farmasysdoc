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

    <div class="ep-screen" wire:key="trainings">
        <div class="ep-nav ep-mobile-only">
            <a href="{{ route('employee-portal.home') }}" class="ep-ghost" wire:navigate>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                Atrás
            </a>
            <p class="ep-step">Capacitaciones</p>
            @include('employee-portal.partials.menu-button')
        </div>

        <div class="ep-home-hero">
            <div>
                <p class="ep-hello">Tu formación</p>
                <h1 class="ep-name">Capacitaciones</h1>
                <p class="ep-text">Revisa tus próximas capacitaciones y descarga tus certificados.</p>
            </div>
        </div>

        <p class="ep-section-label">Próximas</p>
        <div class="ep-stack">
            @forelse ($upcoming as $enrollment)
                <div class="ep-card-btn ep-glass" style="cursor: default;">
                    <span class="ep-card-copy">
                        <strong>{{ $enrollment->session->course->name }}</strong>
                        <span>{{ $enrollment->session->scheduleLabel() }} · {{ $enrollment->session->modality->label() }}</span>
                        @if (filled($enrollment->session->location))
                            <span>{{ $enrollment->session->location }}</span>
                        @endif
                        @if (filled($enrollment->session->meeting_url))
                            <a href="{{ $enrollment->session->meeting_url }}" target="_blank" rel="noopener">Abrir enlace</a>
                        @endif
                    </span>
                </div>
            @empty
                <div class="ep-card-btn ep-glass" style="cursor: default;">
                    <span class="ep-card-copy">
                        <strong>No tienes capacitaciones próximas</strong>
                        <span>Cuando te inscriban, te avisamos por correo y WhatsApp.</span>
                    </span>
                </div>
            @endforelse
        </div>

        @if ($pending->isNotEmpty())
            <p class="ep-section-label">Obligatorias pendientes</p>
            <div class="ep-stack">
                @foreach ($pending as $item)
                    <div class="ep-card-btn ep-glass" style="cursor: default;">
                        <span class="ep-card-copy">
                            <strong>{{ $item['course']->name }}</strong>
                            <span>RR. HH. te avisará la fecha.</span>
                        </span>
                        <span class="ep-pill">{{ $item['status'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="ep-section-label">Historial</p>
        <div class="ep-stack">
            @forelse ($history as $enrollment)
                @if ($enrollment->canDownloadCertificate())
                    <a href="{{ route('employee-portal.trainings.certificate', $enrollment) }}" class="ep-card-btn ep-glass">
                        <span class="ep-card-copy">
                            <strong>{{ $enrollment->session->course->name }}</strong>
                            <span>{{ $enrollment->session->ends_at->format('d/m/Y') }} · {{ $enrollment->session->course->duration_hours + 0 }} h</span>
                        </span>
                        <span class="ep-pill ep-pill--ok">PDF</span>
                    </a>
                @else
                    <div class="ep-card-btn ep-glass" style="cursor: default;">
                        <span class="ep-card-copy">
                            <strong>{{ $enrollment->session->course->name }}</strong>
                            <span>{{ $enrollment->session->ends_at->format('d/m/Y') }} · {{ $enrollment->attendance->label() }}</span>
                        </span>
                    </div>
                @endif
            @empty
                <div class="ep-card-btn ep-glass" style="cursor: default;">
                    <span class="ep-card-copy">
                        <strong>Aún no tienes historial</strong>
                        <span>Aquí aparecerán las capacitaciones que completes.</span>
                    </span>
                </div>
            @endforelse
        </div>
    </div>

    @include('employee-portal.partials.menu-sheet', ['active' => 'trainings'])
</div>
