@php
    $active = $active ?? 'home';
    $employee = $employee ?? null;
    $fileComplete = $employee?->hasCompleteEmployeeFile() ?? false;
    $hasPassword = $employee?->hasPortalPassword() ?? false;
    $links = [
        [
            'key' => 'home',
            'href' => route('employee-portal.home'),
            'title' => 'Inicio',
            'subtitle' => 'Tu portal y accesos',
            'tone' => 'cyan',
        ],
        [
            'key' => 'file',
            'href' => route('employee-portal.file'),
            'title' => 'Expediente',
            'subtitle' => 'Firma y huella',
            'tone' => 'teal',
            'pill' => $fileComplete ? 'Listo' : 'Pendiente',
            'ok' => $fileComplete,
        ],
        [
            'key' => 'account',
            'href' => route('employee-portal.account'),
            'title' => 'Cuenta',
            'subtitle' => 'Clave del portal',
            'tone' => 'amber',
            'pill' => $hasPassword ? 'Activa' : 'Sin clave',
            'ok' => $hasPassword,
        ],
        [
            'key' => 'receipts',
            'href' => route('employee-portal.receipts'),
            'title' => 'Recibos',
            'subtitle' => 'Recibo mensual de ley',
            'tone' => 'cyan',
        ],
    ];
    $soon = [
        ['title' => 'Constancia de trabajo', 'subtitle' => 'Solicítala y descárgala'],
        ['title' => 'Vacaciones', 'subtitle' => 'Pide tus días'],
        ['title' => 'Permiso de ausencia', 'subtitle' => 'Justifica una falta'],
    ];
@endphp

<div
    class="ep-sheet-backdrop ep-menu-backdrop"
    x-show="menuOpen"
    x-cloak
    x-transition.opacity.duration.280ms
    @click="close()"
></div>

<nav
    id="ep-portal-menu"
    class="ep-menu-sheet"
    x-ref="menuSheet"
    x-show="menuOpen"
    x-cloak
    x-bind:class="{ 'is-dragging': dragging }"
    x-bind:style="sheetStyle()"
    x-transition:enter="ep-menu-anim"
    x-transition:enter-start="ep-menu-anim-start"
    x-transition:enter-end="ep-menu-anim-end"
    x-transition:leave="ep-menu-anim ep-menu-anim-leave"
    x-transition:leave-start="ep-menu-anim-end"
    x-transition:leave-end="ep-menu-anim-start"
    role="dialog"
    aria-modal="true"
    aria-labelledby="ep-menu-heading"
    tabindex="-1"
    @click.stop
    @keydown.escape.window="close()"
    @keydown.tab="trapTab($event)"
>
    <button
        type="button"
        class="ep-menu-grab"
        tabindex="-1"
        aria-label="Cerrar menú deslizando hacia abajo"
        @pointerdown="onDragStart($event)"
        @pointermove="onDragMove($event)"
        @pointerup="onDragEnd()"
        @pointercancel="onDragEnd()"
    >
        <span class="ep-sheet-handle"></span>
    </button>

    <p id="ep-menu-heading" class="ep-menu-kicker">Menú</p>

    @if ($employee)
        <div class="ep-menu-user">
            <div class="ep-avatar ep-menu-avatar">
                @if ($employee->hasPhoto())
                    <img src="{{ $employee->photoUrl() }}" alt="">
                @else
                    {{ $employee->initials() }}
                @endif
            </div>
            <div class="ep-menu-user-copy">
                <strong>{{ $employee->fullName() }}</strong>
                <span>C.I. {{ $employee->national_id }}@if ($employee->branch?->name) · {{ $employee->branch->name }}@endif</span>
            </div>
        </div>
    @endif

    <div class="ep-menu-theme">
        <div class="ep-menu-theme-copy">
            <strong>Apariencia</strong>
            <span>Claro u oscuro, como más te guste</span>
        </div>
        @include('employee-portal.partials.theme-toggle', ['size' => 'lg'])
    </div>

    <p class="ep-menu-label">Portal</p>
    <div class="ep-menu-group">
        @foreach ($links as $link)
            <a
                href="{{ $link['href'] }}"
                class="ep-menu-item{{ $active === $link['key'] ? ' is-active' : '' }}"
                wire:navigate
                @click="close()"
            >
                <span class="ep-menu-icon ep-menu-icon--{{ $link['tone'] }}" aria-hidden="true">
                    @include('employee-portal.partials.menu-icon', ['icon' => $link['key']])
                </span>
                <span class="ep-menu-copy">
                    <strong>{{ $link['title'] }}</strong>
                    <span>{{ $link['subtitle'] }}</span>
                </span>
                @if (isset($link['pill']))
                    <span @class(['ep-pill', 'ep-pill--ok' => $link['ok'], 'ep-pill--warn' => ! $link['ok']])>
                        {{ $link['pill'] }}
                    </span>
                @endif
                @if ($active === $link['key'])
                    <span class="ep-menu-check" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </span>
                @else
                    <span class="ep-menu-chevron" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </span>
                @endif
            </a>
        @endforeach
    </div>

    <p class="ep-menu-label">Próximamente</p>
    <div class="ep-menu-group">
        @foreach ($soon as $item)
            <button type="button" class="ep-menu-item is-soon" @click="showSoon()">
                <span class="ep-menu-icon ep-menu-icon--muted" aria-hidden="true">
                    @include('employee-portal.partials.menu-icon', ['icon' => 'soon'])
                </span>
                <span class="ep-menu-copy">
                    <strong>{{ $item['title'] }}</strong>
                    <span>{{ $item['subtitle'] }}</span>
                </span>
                <span class="ep-pill ep-pill--soon">Pronto</span>
            </button>
        @endforeach
    </div>

    <p class="ep-menu-soon-note" x-show="soonHint" x-cloak x-transition.opacity>
        Muy pronto vas a pedir constancias, vacaciones y permisos desde aquí.
    </p>

    <div class="ep-menu-actions">
        <a href="{{ route('employee-portal.logout') }}" class="ep-menu-action ep-menu-action--danger">Salir</a>
        <button type="button" class="ep-menu-action" @click="close()">Cerrar</button>
    </div>
</nav>
