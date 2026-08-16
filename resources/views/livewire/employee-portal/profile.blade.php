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

    <div class="ep-screen" wire:key="profile">
        <div class="ep-nav ep-mobile-only">
            <a href="{{ route('employee-portal.home') }}" class="ep-ghost" wire:navigate>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                Inicio
            </a>
            <p class="ep-step">Perfil</p>
            @include('employee-portal.partials.theme-toggle')
            @include('employee-portal.partials.menu-button')
        </div>

        <div class="ep-flow-intro">
            <div class="ep-profile-hero">
                <div class="ep-avatar ep-profile-photo">
                    @if ($employee->hasPhoto())
                        <img src="{{ $employee->photoUrl() }}" alt="{{ $employee->fullName() }}">
                    @else
                        {{ $employee->initials() }}
                    @endif
                </div>
                <h1 class="ep-lead">{{ $employee->fullName() }}</h1>
                <p class="ep-text">
                    C.I. {{ $employee->formattedNationalId() ?? $employee->national_id }}
                    @if ($employee->branch?->name)
                        · {{ $employee->branch->name }}
                    @endif
                </p>
            </div>

            <p class="ep-section-label">Datos personales</p>
            <dl class="ep-info-list ep-glass">
                @foreach ($fields as $field)
                    <div class="ep-info-row">
                        <dt>{{ $field['label'] }}</dt>
                        <dd>{{ $field['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    @include('employee-portal.partials.menu-sheet', ['active' => 'profile'])
</div>
