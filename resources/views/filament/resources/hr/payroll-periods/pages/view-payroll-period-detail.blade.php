@php
    /** @var \App\Models\PayrollPeriod $period */
    $period = $this->getRecord();
    $employeesCount = $period->lines()->count();
@endphp

<x-filament-panels::page>
    <div class="fi-hr-payroll-detail">
        @include('filament.resources.hr.payroll-periods.partials.period-summary-hero', [
            'period' => $period,
            'employeesCount' => $employeesCount,
        ])

        <div class="fi-hr-payroll-detail__table">
            <div class="fi-hr-payroll-detail__table-heading">
                <h3>Detalle por empleado</h3>
                <p>Valida base, asignaciones, deducciones y préstamos de cada pago quincenal.</p>
            </div>

            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
