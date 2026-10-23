@php
    use App\Enums\EmployeeRequestStatus;

    $statusPill = fn (EmployeeRequestStatus $status): string => match ($status) {
        EmployeeRequestStatus::Approved => 'ep-pill--ok',
        EmployeeRequestStatus::Pending => 'ep-pill--warn',
        EmployeeRequestStatus::Rejected => 'ep-pill--danger',
        default => 'ep-pill--soon',
    };
@endphp

<div class="ep-app ep-app--flow" x-data="employeePortalMenu">
    @include('employee-portal.partials.page-header', ['backHref' => route('employee-portal.home')])

    <div class="ep-screen" wire:key="requests">
        @include('employee-portal.partials.page-nav', ['backHref' => route('employee-portal.home'), 'step' => 'Solicitudes'])

        <div class="ep-home-hero">
            <div>
                <p class="ep-hello">RR. HH.</p>
                <h1 class="ep-name">Mis solicitudes</h1>
                <p class="ep-text">Aquí ves el estado de cada solicitud. Cuando RR. HH. responda te avisamos por correo y WhatsApp.</p>
            </div>
        </div>

        @if ($flash)
            <p class="ep-toast">{{ $flash }}</p>
        @endif
        @if ($errorMessage)
            <p class="ep-error">{{ $errorMessage }}</p>
        @endif

        <div
            class="ep-badge-optin ep-glass"
            x-data="{ show: false }"
            x-init="show = $store.epNotifications.canAskPermission()"
            x-show="show"
            x-cloak
        >
            <span class="ep-card-copy">
                <strong>Avisos en el ícono</strong>
                <span>Activa los avisos para ver el número de novedades en el ícono del portal.</span>
            </span>
            <button type="button" class="ep-btn ep-btn--secondary ep-btn--compact" x-on:click="$store.epNotifications.askPermission().then(() => show = false)">Activar</button>
        </div>

        <p class="ep-section-label">Nueva solicitud</p>
        <div class="ep-request-shortcuts">
            <a href="{{ route('employee-portal.requests.certificate') }}" class="ep-request-shortcut ep-glass" wire:navigate>
                <span class="ep-menu-icon ep-menu-icon--cyan" aria-hidden="true">@include('employee-portal.partials.menu-icon', ['icon' => 'employment_certificate'])</span>
                Constancia
            </a>
            <a href="{{ route('employee-portal.requests.absence', 'vacaciones') }}" class="ep-request-shortcut ep-glass" wire:navigate>
                <span class="ep-menu-icon ep-menu-icon--amber" aria-hidden="true">@include('employee-portal.partials.menu-icon', ['icon' => 'vacation'])</span>
                Vacaciones
            </a>
            <a href="{{ route('employee-portal.requests.absence', 'permiso') }}" class="ep-request-shortcut ep-glass" wire:navigate>
                <span class="ep-menu-icon ep-menu-icon--teal" aria-hidden="true">@include('employee-portal.partials.menu-icon', ['icon' => 'leave'])</span>
                Permiso
            </a>
            <a href="{{ route('employee-portal.requests.absence', 'reposo') }}" class="ep-request-shortcut ep-glass" wire:navigate>
                <span class="ep-menu-icon ep-menu-icon--rose" aria-hidden="true">@include('employee-portal.partials.menu-icon', ['icon' => 'medical_rest'])</span>
                Reposo
            </a>
        </div>

        <p class="ep-section-label">Historial</p>
        <div class="ep-stack">
            @forelse ($requests as $request)
                <article
                    @class(['ep-request-card', 'ep-glass', 'is-fresh' => in_array($request->id, $freshIds, true)])
                    wire:key="request-{{ $request->id }}"
                >
                    <div class="ep-request-card__head">
                        <span class="ep-card-icon" aria-hidden="true">
                            @include('employee-portal.partials.menu-icon', ['icon' => $request->type->value])
                        </span>
                        <span class="ep-card-copy">
                            <strong>{{ $request->type->label() }}</strong>
                            <span>{{ $request->summaryLabel() }}</span>
                        </span>
                        <span @class(['ep-pill', $statusPill($request->status)])>{{ $request->status->label() }}</span>
                    </div>

                    @if ($request->is_early)
                        <p class="ep-request-card__note">Anticipadas: tu derecho nace el {{ $request->entitlement_starts_on?->format('d/m/Y') }}.</p>
                    @endif

                    @if (filled($request->reason))
                        <p class="ep-request-card__note"><strong>Motivo:</strong> {{ $request->reason }}</p>
                    @endif

                    @if (filled($request->admin_note))
                        <p class="ep-request-card__note ep-request-card__note--admin"><strong>RR. HH.:</strong> {{ $request->admin_note }}</p>
                    @endif

                    @if ($request->attachments->isNotEmpty())
                        <div class="ep-request-card__files">
                            @foreach ($request->attachments as $attachment)
                                <a href="{{ route('employee-portal.requests.attachment', $attachment) }}" target="_blank" rel="noopener" class="ep-file-chip">
                                    {{ \Illuminate\Support\Str::limit($attachment->original_name, 28) }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div class="ep-request-card__foot">
                        <span>Enviada el {{ $request->created_at?->format('d/m/Y') }}</span>

                        @if ($request->canDownloadCertificate())
                            <a href="{{ route('employee-portal.requests.certificate.download', $request) }}" class="ep-btn ep-btn--primary ep-btn--compact">Descargar constancia</a>
                        @elseif ($request->isPending())
                            <button
                                type="button"
                                class="ep-btn ep-btn--secondary ep-btn--compact"
                                wire:click="cancel({{ $request->id }})"
                                wire:confirm="¿Seguro que quieres anular esta solicitud?"
                                wire:loading.attr="disabled"
                                wire:target="cancel({{ $request->id }})"
                            >Anular</button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="ep-card-btn ep-glass" style="cursor: default;">
                    <span class="ep-card-copy">
                        <strong>Aún no tienes solicitudes</strong>
                        <span>Elige arriba qué necesitas pedir.</span>
                    </span>
                </div>
            @endforelse
        </div>
    </div>

    @include('employee-portal.partials.menu-sheet', ['active' => 'requests'])
</div>
