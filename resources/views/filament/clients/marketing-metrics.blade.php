@php
    /** @var array<string, mixed> $m */
    $byBranch = $m['purchases_by_branch'] ?? [];
    $topProducts = $m['top_products'] ?? [];
@endphp
<div class="fi-client-marketing-metrics fi-client-marketing-metrics--minimal">
    <div class="fi-client-marketing-metrics__hero">
        <div class="fi-client-marketing-metrics__hero-main">
            <p class="fi-client-marketing-metrics__eyebrow">Compras completadas</p>
            <p class="fi-client-marketing-metrics__hero-stat">{{ $m['purchases_count'] }}</p>
        </div>
        <div class="fi-client-marketing-metrics__hero-accent" aria-hidden="true"></div>
        <dl class="fi-client-marketing-metrics__hero-secondary">
            <div class="fi-client-marketing-metrics__hero-kv">
                <dt>Total gastado</dt>
                <dd>{{ $m['total_spent'] }}</dd>
            </div>
            <div class="fi-client-marketing-metrics__hero-kv">
                <dt>Ticket promedio</dt>
                <dd>{{ $m['avg_ticket'] }}</dd>
            </div>
        </dl>
    </div>

    <div class="fi-client-marketing-metrics__split">
        <section class="fi-client-marketing-metrics__panel" aria-labelledby="client-mkt-branches">
            <h3 id="client-mkt-branches" class="fi-client-marketing-metrics__panel-title">Compras por sucursal</h3>
            @if ($byBranch === [])
                <p class="fi-client-marketing-metrics__empty">Sin ventas completadas por sucursal.</p>
            @else
                <ul class="fi-client-marketing-metrics__branch-list">
                    @foreach ($byBranch as $row)
                        <li class="fi-client-marketing-metrics__branch-row">
                            <span class="fi-client-marketing-metrics__branch-name">{{ $row['branch_name'] }}</span>
                            <span class="fi-client-marketing-metrics__branch-count">{{ $row['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="fi-client-marketing-metrics__panel fi-client-marketing-metrics__panel--top" aria-labelledby="client-mkt-top">
            <h3 id="client-mkt-top" class="fi-client-marketing-metrics__panel-title">Top 5 productos más comprados</h3>
            @if ($topProducts === [])
                <p class="fi-client-marketing-metrics__empty">Sin líneas de venta en compras completadas.</p>
            @else
                <ol class="fi-client-marketing-metrics__top-list">
                    @foreach ($topProducts as $idx => $p)
                        <li class="fi-client-marketing-metrics__top-item">
                            <span class="fi-client-marketing-metrics__top-rank">{{ $idx + 1 }}</span>
                            <span class="fi-client-marketing-metrics__top-name">{{ $p['name'] }}</span>
                            <span class="fi-client-marketing-metrics__top-qty" title="Cantidad total vendida (unidades)">{{ $p['quantity_label'] }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    <div class="fi-client-marketing-metrics__meta">
        <span class="fi-client-marketing-metrics__meta-item">
            <span class="fi-client-marketing-metrics__meta-label">Compra más alta</span>
            <span class="fi-client-marketing-metrics__meta-value">{{ $m['max_purchase'] }}</span>
        </span>
        <span class="fi-client-marketing-metrics__meta-sep" aria-hidden="true"></span>
        <span class="fi-client-marketing-metrics__meta-item">
            <span class="fi-client-marketing-metrics__meta-label">Sucursales distintas</span>
            <span class="fi-client-marketing-metrics__meta-value">{{ $m['branches_visited'] }}</span>
        </span>
        <span class="fi-client-marketing-metrics__meta-sep" aria-hidden="true"></span>
        <span class="fi-client-marketing-metrics__meta-item">
            <span class="fi-client-marketing-metrics__meta-label">Primera compra</span>
            <span class="fi-client-marketing-metrics__meta-value">{{ $m['first_purchase_at'] ?? '—' }}</span>
        </span>
        <span class="fi-client-marketing-metrics__meta-sep" aria-hidden="true"></span>
        <span class="fi-client-marketing-metrics__meta-item">
            <span class="fi-client-marketing-metrics__meta-label">Última compra</span>
            <span class="fi-client-marketing-metrics__meta-value">{{ $m['last_purchase_at'] ?? '—' }}</span>
        </span>
    </div>
</div>
