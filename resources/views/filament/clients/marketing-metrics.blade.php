@php
    /** @var array<string, mixed> $m */
@endphp
<div class="fi-client-marketing-metrics">
    <div class="fi-client-marketing-metrics__card fi-client-marketing-metrics__card--kpi">
        <p class="fi-client-marketing-metrics__label">Compras completadas</p>
        <p class="fi-client-marketing-metrics__value fi-client-marketing-metrics__value--kpi">{{ $m['purchases_count'] }}</p>
    </div>
    <div class="fi-client-marketing-metrics__card fi-client-marketing-metrics__card--spotlight">
        <p class="fi-client-marketing-metrics__label fi-client-marketing-metrics__label--spotlight">Producto más comprado</p>
        <p class="fi-client-marketing-metrics__value fi-client-marketing-metrics__value--spotlight">{{ $m['favorite_product'] }}</p>
    </div>
    <div class="fi-client-marketing-metrics__card fi-client-marketing-metrics__card--money">
        <p class="fi-client-marketing-metrics__label">Total gastado</p>
        <p class="fi-client-marketing-metrics__value fi-client-marketing-metrics__value--money">{{ $m['total_spent'] }}</p>
    </div>
    <div class="fi-client-marketing-metrics__card fi-client-marketing-metrics__card--money">
        <p class="fi-client-marketing-metrics__label">Compra más alta</p>
        <p class="fi-client-marketing-metrics__value fi-client-marketing-metrics__value--money">{{ $m['max_purchase'] }}</p>
    </div>
    <div class="fi-client-marketing-metrics__card fi-client-marketing-metrics__card--money">
        <p class="fi-client-marketing-metrics__label">Ticket promedio</p>
        <p class="fi-client-marketing-metrics__value fi-client-marketing-metrics__value--money">{{ $m['avg_ticket'] }}</p>
    </div>
    <div class="fi-client-marketing-metrics__card fi-client-marketing-metrics__card--kpi">
        <p class="fi-client-marketing-metrics__label">Sucursales distintas</p>
        <p class="fi-client-marketing-metrics__value fi-client-marketing-metrics__value--kpi">{{ $m['branches_visited'] }}</p>
    </div>
    <div class="fi-client-marketing-metrics__card fi-client-marketing-metrics__card--timeline">
        <p class="fi-client-marketing-metrics__label">Última compra</p>
        <p class="fi-client-marketing-metrics__value fi-client-marketing-metrics__value--timeline">{{ $m['last_purchase_at'] ?? '—' }}</p>
    </div>
    <div class="fi-client-marketing-metrics__card fi-client-marketing-metrics__card--timeline">
        <p class="fi-client-marketing-metrics__label">Primera compra</p>
        <p class="fi-client-marketing-metrics__value fi-client-marketing-metrics__value--timeline">{{ $m['first_purchase_at'] ?? '—' }}</p>
    </div>
</div>
