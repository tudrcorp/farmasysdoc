@php
    $graceDays = (int) ($graceDays ?? 5);
    $overdueLabel = $overdueLabel ?? null;
    $remainingDays = $remainingDays ?? null;
    $visibleUntil = $visibleUntil ?? null;
    $hasOverdue = filled($overdueLabel) && $remainingDays !== null;
    $remainingText = $remainingDays === 0
        ? 'último día de visibilidad'
        : $remainingDays.' '.($remainingDays === 1 ? 'día restante' : 'días restantes');
@endphp

<aside class="fi-hr-payroll-visibility" aria-label="Visibilidad de periodos de nómina">
    <details class="fi-hr-payroll-visibility__details">
        <summary class="fi-hr-payroll-visibility__summary">
            <span class="fi-hr-payroll-visibility__summary-text">
                <span class="fi-hr-payroll-visibility__eyebrow">Funcionamiento del módulo</span>
                <span class="fi-hr-payroll-visibility__title">Qué periodos se muestran en esta tabla</span>
                <span class="fi-hr-payroll-visibility__hint">Clic para leer las reglas de visibilidad</span>
            </span>
            <span class="fi-hr-payroll-visibility__chevron" aria-hidden="true"></span>
        </summary>

        <div class="fi-hr-payroll-visibility__body">
            <p class="fi-hr-payroll-visibility__lead">
                El sistema no lista los 24 periodos del año. Solo deja visible el periodo que corresponde pagar.
                Tras la fecha de pago, ese periodo permanece {{ $graceDays }} días más para que pueda cerrarse o consultarse;
                en ese tramo conviven el periodo atrasado y el nuevo. Si el periodo ya está calculado, se oculta;
                para verlo de nuevo, use los filtros de periodo o estatus.
            </p>

            <ul class="fi-hr-payroll-visibility__rules">
                <li>
                    <strong>1.ª quincena:</strong>
                    visible del día 1 al 15 y hasta {{ $graceDays }} días después del pago (ejemplo: 15 de agosto visible hasta el 20).
                </li>
                <li>
                    <strong>2.ª quincena:</strong>
                    visible desde el día 15 hasta el cierre de mes, más {{ $graceDays }} días después de la fecha de pago.
                </li>
                <li>
                    <strong>Solape:</strong>
                    entre el día de pago y el final de esos {{ $graceDays }} días verá dos filas: el atrasado y el periodo nuevo.
                </li>
                <li>
                    <strong>Calculado:</strong>
                    un periodo en estatus Calculada deja de mostrarse. Para consultarlo, elija el periodo o marque Calculada en los filtros.
                </li>
            </ul>
        </div>
    </details>

    @if ($hasOverdue)
        <div class="fi-hr-payroll-visibility__overdue" role="status">
            <p class="fi-hr-payroll-visibility__overdue-label">Periodo atrasado</p>
            <p class="fi-hr-payroll-visibility__overdue-value">{{ $remainingText }}</p>
            <p class="fi-hr-payroll-visibility__overdue-meta">
                {{ $overdueLabel }}
                @if (filled($visibleUntil))
                    · visible hasta el {{ $visibleUntil }}
                @endif
            </p>
        </div>
    @endif
</aside>
