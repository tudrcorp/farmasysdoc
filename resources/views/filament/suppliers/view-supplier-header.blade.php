@php
    $money = static fn (float $value): string => number_format($value, 2, ',', '.');
    $retentionPercent = $supplier->seniat_retention_percent;
    $country = filled($supplier->country) ? (string) $supplier->country : null;
@endphp

<span class="fi-farmaadmin-supplier-header">
    @if (filled($supplier->trade_name) && $supplier->trade_name !== $supplier->legal_name)
        <span class="fi-farmaadmin-supplier-header__trade-name">{{ $supplier->trade_name }}</span>
    @endif

    <span class="fi-farmaadmin-supplier-header__chips">
        <span class="fi-farmaadmin-supplier-header__chip">
            <x-filament::icon icon="heroicon-o-hashtag" class="size-3.5" />
            {{ $supplier->code ?: \App\Models\Supplier::formatCode($supplier->getKey()) }}
        </span>
        @if (filled($supplier->tax_id))
            <span class="fi-farmaadmin-supplier-header__chip">
                <x-filament::icon icon="heroicon-o-identification" class="size-3.5" />
                RIF {{ $supplier->tax_id }}
            </span>
        @endif
        @if ($country)
            <span class="fi-farmaadmin-supplier-header__chip">
                <x-filament::icon icon="heroicon-o-globe-americas" class="size-3.5" />
                {{ $country }}
            </span>
        @endif
        @if ($retentionPercent !== null)
            <span class="fi-farmaadmin-supplier-header__chip fi-farmaadmin-supplier-header__chip--accent">
                <x-filament::icon icon="heroicon-o-receipt-percent" class="size-3.5" />
                Retención IVA {{ number_format((float) $retentionPercent, 2, ',', '.') }}%
            </span>
        @else
            <span class="fi-farmaadmin-supplier-header__chip fi-farmaadmin-supplier-header__chip--warning" title="Requerido para registrar compras con retención SENIAT">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="size-3.5" />
                Sin % de retención
            </span>
        @endif
        <span @class([
            'fi-farmaadmin-supplier-header__chip',
            'fi-farmaadmin-supplier-header__chip--success' => $supplier->is_active,
            'fi-farmaadmin-supplier-header__chip--muted' => ! $supplier->is_active,
        ])>
            {{ $supplier->is_active ? 'Activo' : 'Inactivo' }}
        </span>
    </span>

    <span class="fi-farmaadmin-supplier-header__stats">
        <span class="fi-farmaadmin-supplier-header__stat">
            <span class="fi-farmaadmin-supplier-header__stat-label">Total comprado</span>
            <span class="fi-farmaadmin-supplier-header__stat-value">${{ $money($summary['purchased_usd']) }}</span>
        </span>
        <span @class([
            'fi-farmaadmin-supplier-header__stat',
            'fi-farmaadmin-supplier-header__stat--warning' => $summary['pending_usd'] > 0,
        ])>
            <span class="fi-farmaadmin-supplier-header__stat-label">Pendiente por pagar</span>
            <span class="fi-farmaadmin-supplier-header__stat-value">
                ${{ $money($summary['pending_usd']) }}
                @if ($summary['pending_invoices_count'] > 0)
                    <span class="fi-farmaadmin-supplier-header__stat-extra">· {{ $summary['pending_invoices_count'] }} factura(s)</span>
                @endif
            </span>
        </span>
        <span class="fi-farmaadmin-supplier-header__stat">
            <span class="fi-farmaadmin-supplier-header__stat-label">Última compra</span>
            <span class="fi-farmaadmin-supplier-header__stat-value">{{ $summary['last_purchase_at'] ?? '—' }}</span>
        </span>
    </span>
</span>
