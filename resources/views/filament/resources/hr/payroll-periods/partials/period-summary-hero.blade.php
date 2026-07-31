@php
    /** @var \App\Models\PayrollPeriod $period */
    use App\Enums\PayrollPeriodStatus;

    $status = $period->status instanceof PayrollPeriodStatus
        ? $period->status
        : PayrollPeriodStatus::tryFrom((string) $period->status);

    $statusLabel = $status?->label() ?? '—';
    $statusClass = match ($status) {
        PayrollPeriodStatus::Draft => 'is-draft',
        PayrollPeriodStatus::Calculated => 'is-calculated',
        PayrollPeriodStatus::Closed => 'is-closed',
        default => 'is-draft',
    };

    $employeesCount = (int) ($employeesCount ?? 0);
    $rate = $period->bcv_ves_per_usd
        ? number_format((float) $period->bcv_ves_per_usd, 4, ',', '.')
        : '—';
@endphp

<div class="fi-hr-payroll-hero" data-fi-hr-payroll-hero>
    <div class="fi-hr-payroll-hero__top">
        <div class="fi-hr-payroll-hero__identity">
            <div class="fi-hr-payroll-hero__title-row">
                <p class="fi-hr-payroll-hero__eyebrow">Resumen del periodo</p>
                <span class="fi-hr-payroll-hero__badge fi-hr-payroll-hero__badge--{{ $statusClass }}">
                    {{ $statusLabel }}
                </span>
            </div>
            <p class="fi-hr-payroll-hero__meta">
                <span>{{ $employeesCount }} {{ $employeesCount === 1 ? 'empleado' : 'empleados' }}</span>
                <span class="fi-hr-payroll-hero__dot" aria-hidden="true">·</span>
                <span>Tasa BCV {{ $rate }}</span>
                @if ($period->calculated_at)
                    <span class="fi-hr-payroll-hero__dot" aria-hidden="true">·</span>
                    <span>Calculada {{ $period->calculated_at->format('d/m/Y H:i') }}</span>
                @endif
            </p>
        </div>
    </div>

    <div class="fi-hr-payroll-hero__stats" role="list">
        <div class="fi-hr-payroll-hero__stat fi-hr-payroll-hero__stat--payable" role="listitem">
            <p class="fi-hr-payroll-hero__stat-label">Total a pagar</p>
            <p class="fi-hr-payroll-hero__stat-value">
                US$ {{ number_format((float) $period->total_payable_usd, 2, ',', '.') }}
            </p>
            <p class="fi-hr-payroll-hero__stat-sub">
                Bs {{ number_format((float) $period->total_payable_ves, 2, ',', '.') }}
            </p>
        </div>
        <div class="fi-hr-payroll-hero__stat fi-hr-payroll-hero__stat--plus" role="listitem">
            <p class="fi-hr-payroll-hero__stat-label">Asignaciones</p>
            <p class="fi-hr-payroll-hero__stat-value">
                US$ {{ number_format((float) $period->total_assignments_usd, 2, ',', '.') }}
            </p>
            <p class="fi-hr-payroll-hero__stat-sub">
                Bs {{ number_format((float) $period->total_assignments_ves, 2, ',', '.') }}
            </p>
        </div>
        <div class="fi-hr-payroll-hero__stat fi-hr-payroll-hero__stat--minus" role="listitem">
            <p class="fi-hr-payroll-hero__stat-label">Descuentos</p>
            <p class="fi-hr-payroll-hero__stat-value">
                US$ {{ number_format((float) $period->total_deductions_usd, 2, ',', '.') }}
            </p>
            <p class="fi-hr-payroll-hero__stat-sub">
                Bs {{ number_format((float) $period->total_deductions_ves, 2, ',', '.') }}
            </p>
        </div>
        <div class="fi-hr-payroll-hero__stat fi-hr-payroll-hero__stat--loan" role="listitem">
            <p class="fi-hr-payroll-hero__stat-label">Préstamos</p>
            <p class="fi-hr-payroll-hero__stat-value">
                US$ {{ number_format((float) $period->total_loans_usd, 2, ',', '.') }}
            </p>
            <p class="fi-hr-payroll-hero__stat-sub">
                Bs {{ number_format((float) $period->total_loans_ves, 2, ',', '.') }}
            </p>
        </div>
    </div>
</div>
