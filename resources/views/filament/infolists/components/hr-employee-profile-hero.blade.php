@php
    /** @var array<string, mixed> $data */
    $initials = $data['initials'] ?? '—';
    $photoUrl = $data['photo_url'] ?? null;
    $fullName = $data['full_name'] ?? '—';
    $nationalId = $data['national_id'] ?? '—';
    $branch = $data['branch'] ?? '—';
    $isActive = (bool) ($data['is_active'] ?? false);
    $monthlyUsd = $data['monthly_usd'] ?? '—';
    $monthlyVes = $data['monthly_ves'] ?? null;
    $biweeklyUsd = $data['biweekly_usd'] ?? '—';
    $biweeklyVes = $data['biweekly_ves'] ?? null;
    $rateLabel = $data['rate_label'] ?? null;
    $assignmentsCount = (int) ($data['assignments_count'] ?? 0);
    $deductionsCount = (int) ($data['deductions_count'] ?? 0);
    $activeLoansCount = (int) ($data['active_loans_count'] ?? 0);
    $loanRemainingUsd = $data['loan_remaining_usd'] ?? 'US$ 0,00';
    $phone = $data['phone'] ?? null;
    $email = $data['email'] ?? null;
@endphp

<div class="fi-hr-employee-hero" data-fi-hr-employee-hero>
    <div class="fi-hr-employee-hero__profile">
        <div class="fi-hr-employee-hero__avatar" aria-hidden="true">
            @if (filled($photoUrl))
                <img src="{{ $photoUrl }}" alt="" class="fi-hr-employee-hero__avatar-img">
            @else
                <span>{{ $initials }}</span>
            @endif
        </div>
        <div class="fi-hr-employee-hero__identity">
            <div class="fi-hr-employee-hero__title-row">
                <h2 class="fi-hr-employee-hero__name">{{ $fullName }}</h2>
                <span @class([
                    'fi-hr-employee-hero__badge',
                    'fi-hr-employee-hero__badge--active' => $isActive,
                    'fi-hr-employee-hero__badge--inactive' => ! $isActive,
                ])>
                    {{ $isActive ? 'Activo' : 'Inactivo' }}
                </span>
            </div>
            <p class="fi-hr-employee-hero__meta">
                <span>C.I. {{ $nationalId }}</span>
                <span class="fi-hr-employee-hero__dot" aria-hidden="true">·</span>
                <span>{{ $branch }}</span>
            </p>
            @if (filled($phone) || filled($email))
                <p class="fi-hr-employee-hero__contact">
                    @if (filled($phone))
                        <span>{{ $phone }}</span>
                    @endif
                    @if (filled($phone) && filled($email))
                        <span class="fi-hr-employee-hero__dot" aria-hidden="true">·</span>
                    @endif
                    @if (filled($email))
                        <span>{{ $email }}</span>
                    @endif
                </p>
            @endif
        </div>
    </div>

    <div class="fi-hr-employee-hero__stats" role="list">
        <div class="fi-hr-employee-hero__stat fi-hr-employee-hero__stat--primary" role="listitem">
            <p class="fi-hr-employee-hero__stat-label">Sueldo mensual</p>
            <p class="fi-hr-employee-hero__stat-value">{{ $monthlyUsd }}</p>
            @if (filled($monthlyVes))
                <p class="fi-hr-employee-hero__stat-sub">{{ $monthlyVes }}</p>
            @endif
            @if (filled($rateLabel))
                <p class="fi-hr-employee-hero__stat-hint">{{ $rateLabel }}</p>
            @endif
        </div>
        <div class="fi-hr-employee-hero__stat" role="listitem">
            <p class="fi-hr-employee-hero__stat-label">Pago quincenal</p>
            <p class="fi-hr-employee-hero__stat-value">{{ $biweeklyUsd }}</p>
            @if (filled($biweeklyVes))
                <p class="fi-hr-employee-hero__stat-sub">{{ $biweeklyVes }}</p>
            @endif
            <p class="fi-hr-employee-hero__stat-hint">Mitad del sueldo (15 / cierre de mes)</p>
        </div>
        <div class="fi-hr-employee-hero__stat" role="listitem">
            <p class="fi-hr-employee-hero__stat-label">Préstamos activos</p>
            <p class="fi-hr-employee-hero__stat-value">{{ $activeLoansCount }}</p>
            <p class="fi-hr-employee-hero__stat-sub">Saldo {{ $loanRemainingUsd }}</p>
            <p class="fi-hr-employee-hero__stat-hint">Asig. {{ $assignmentsCount }} · Ded. {{ $deductionsCount }}</p>
        </div>
    </div>
</div>
