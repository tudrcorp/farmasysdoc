{{-- Ilustración tablero: colores vía CSS variables (claro / oscuro). --}}
<div class="fi-bp-doc-figure fi-bp-doc-figure--dashboard" role="img" aria-label="Esquema del tablero con tarjetas y gráfico">
    <svg
        viewBox="0 0 640 288"
        class="fi-bp-doc-figure__svg"
        preserveAspectRatio="xMidYMid meet"
        overflow="visible"
        xmlns="http://www.w3.org/2000/svg"
    >
        <text x="12" y="22" fill="currentColor" font-size="11" font-weight="600" opacity="0.88">Inicio — orden visual aproximado</text>
        <rect x="12" y="32" width="616" height="40" rx="14" fill="var(--fi-bp-doc-chart-bg)" stroke="var(--fi-bp-doc-chart-border)" stroke-width="1" />
        <text x="28" y="56" fill="currentColor" font-size="9" opacity="0.62">Bienvenida / datos de su cuenta (siempre visible)</text>
        @if ($hasAssignedCredit ?? false)
            <rect x="12" y="82" width="616" height="52" rx="14" fill="var(--fi-bp-doc-credit-fill)" stroke="var(--fi-bp-doc-credit-stroke)" stroke-width="1" />
            <text x="28" y="100" fill="currentColor" font-size="9" font-weight="600">Línea de crédito</text>
            <text x="28" y="114" fill="currentColor" font-size="8" font-weight="500" opacity="0.88">Disponible, tope y consumido</text>
            <text x="28" y="127" fill="currentColor" font-size="7" opacity="0.55">Se actualiza solo cada pocos segundos</text>
            @php $yStats = 142; @endphp
        @else
            <rect x="12" y="82" width="616" height="32" rx="12" fill="none" stroke="var(--fi-bp-doc-placeholder-stroke)" stroke-width="1" stroke-dasharray="6 4" />
            <text x="28" y="102" fill="currentColor" font-size="8" opacity="0.52">Sin línea de crédito: esta franja no aparece en su panel</text>
            @php $yStats = 124; @endphp
        @endif
        <rect x="12" y="{{ $yStats }}" width="196" height="52" rx="14" fill="var(--fi-bp-doc-red-fill)" stroke="var(--fi-bp-doc-red-stroke)" stroke-width="1" />
        <text x="110" y="{{ $yStats + 22 }}" text-anchor="middle" fill="currentColor" font-size="8" font-weight="600">Pendiente</text>
        <text x="110" y="{{ $yStats + 40 }}" text-anchor="middle" fill="currentColor" font-size="7" opacity="0.55">conteo en vivo</text>
        <rect x="222" y="{{ $yStats }}" width="196" height="52" rx="14" fill="var(--fi-bp-doc-amber-fill)" stroke="var(--fi-bp-doc-amber-stroke)" stroke-width="1" />
        <text x="320" y="{{ $yStats + 22 }}" text-anchor="middle" fill="currentColor" font-size="8" font-weight="600">En proceso</text>
        <rect x="432" y="{{ $yStats }}" width="196" height="52" rx="14" fill="var(--fi-bp-doc-green-fill)" stroke="var(--fi-bp-doc-green-stroke)" stroke-width="1" />
        <text x="530" y="{{ $yStats + 22 }}" text-anchor="middle" fill="currentColor" font-size="8" font-weight="600">Finalizado</text>
        <rect x="12" y="{{ $yStats + 62 }}" width="616" height="58" rx="14" fill="var(--fi-bp-doc-chart-bg)" stroke="var(--fi-bp-doc-chart-border)" stroke-width="1" />
        <text x="320" y="{{ $yStats + 78 }}" text-anchor="middle" fill="currentColor" font-size="9" font-weight="600" opacity="0.72">Gráfico — pedidos finalizados por mes (barras US$ · línea cantidad)</text>
        @php
            $chartBottom = $yStats + 62 + 58;
            $barBaseline = $chartBottom - 10;
        @endphp
        <g opacity="0.88">
            <rect x="42" y="{{ $barBaseline - 20 }}" width="22" height="20" rx="4" fill="var(--fi-bp-doc-teal-bar)" opacity="0.88" />
            <rect x="82" y="{{ $barBaseline - 14 }}" width="22" height="14" rx="4" fill="var(--fi-bp-doc-teal-bar)" opacity="0.78" />
            <rect x="122" y="{{ $barBaseline - 24 }}" width="22" height="24" rx="4" fill="var(--fi-bp-doc-teal-bar)" />
        </g>
    </svg>
</div>
