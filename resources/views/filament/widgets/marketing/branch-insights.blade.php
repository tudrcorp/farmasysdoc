@php
    /** @var \Illuminate\Support\Collection<int, object> $ranking */
    /** @var \Illuminate\Support\Collection<int, string> $branchOptions */
    /** @var ?array{name: string, top_product: string, top_clients: \Illuminate\Support\Collection<int, object>} $detail */
    /** @var ?string $selectedBranchId */
@endphp

<x-filament-widgets::widget class="fi-wi-widget fi-marketing-branch-insights">
    <div class="fi-sc-section fi-client-marketing-behavior">
        <x-filament::section
            heading="Sucursales y clientes"
            description="Ranking por ventas completadas. Toca una fila o elige sucursal para ver producto top y el top 10 de clientes."
        >
            <div class="fi-client-marketing-metrics--minimal">
                <div class="fi-marketing-branch-insights__toolbar">
                    <div class="fi-marketing-branch-insights__toolbar-inner">
                        <label class="fi-marketing-branch-insights__field">
                            <span class="fi-marketing-branch-insights__field-label fi-client-marketing-metrics__panel-title">
                                Filtrar detalle por sucursal
                            </span>
                            <x-filament::input.wrapper class="fi-marketing-branch-insights__select-wrap">
                                <x-filament::input.select
                                    wire:model.live="selectedBranchId"
                                    class="fi-marketing-branch-insights__select"
                                >
                                    <option value="">— Ver solo el ranking general —</option>
                                    @foreach ($branchOptions as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </label>

                        @if (filled($selectedBranchId))
                            <button
                                type="button"
                                wire:click="clearBranchDetail"
                                class="fi-marketing-branch-insights__reset"
                            >
                                Quitar selección
                            </button>
                        @endif
                    </div>
                </div>

                <div
                    class="fi-marketing-branch-insights__grid"
                    wire:loading.class="fi-marketing-branch-insights__grid--loading"
                >
                    <div class="fi-marketing-branch-insights__panel fi-marketing-branch-insights__panel--table fi-client-marketing-metrics__panel">
                        <div class="fi-marketing-branch-insights__panel-head">
                            <h3 class="fi-marketing-branch-insights__panel-title fi-client-marketing-metrics__panel-title">Ranking de sucursales</h3>
                            <p class="fi-marketing-branch-insights__panel-sub">
                                Por ventas completadas · fila interactiva
                            </p>
                        </div>
                        <div class="fi-marketing-branch-insights__table-scroll">
                            <table class="fi-marketing-branch-insights__table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Sucursal</th>
                                        <th class="fi-marketing-branch-insights__th-num">Ventas</th>
                                        <th class="fi-marketing-branch-insights__th-num">Ingresos</th>
                                        <th>Producto top</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($ranking as $index => $row)
                                        <tr
                                            wire:key="rank-{{ $row->branch_id }}"
                                            wire:click="toggleBranch({{ $row->branch_id }})"
                                            wire:keydown.enter.prevent="toggleBranch({{ $row->branch_id }})"
                                            tabindex="0"
                                            @class([
                                                'fi-marketing-branch-insights__row',
                                                'fi-marketing-branch-insights__row--active' => (string) $selectedBranchId === (string) $row->branch_id,
                                            ])
                                        >
                                            <td class="fi-marketing-branch-insights__cell-num">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="fi-marketing-branch-insights__cell-strong">
                                                {{ $row->branch_name }}
                                            </td>
                                            <td class="fi-marketing-branch-insights__cell-num">
                                                {{ number_format($row->sales_count, 0, ',', '.') }}
                                            </td>
                                            <td class="fi-marketing-branch-insights__cell-num">
                                                {{ $row->revenue_formatted }}
                                            </td>
                                            <td
                                                class="fi-marketing-branch-insights__cell-truncate"
                                                title="{{ $row->top_product }}"
                                            >
                                                {{ $row->top_product }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="fi-marketing-branch-insights__empty">
                                                No hay ventas completadas con sucursal asignada.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div
                        class="fi-marketing-branch-insights__detail-stack"
                        id="fi-marketing-branch-detail"
                    >
                        @if ($detail)
                            <div class="fi-marketing-branch-insights__panel fi-marketing-branch-insights__panel--hero fi-client-marketing-metrics__panel fi-client-marketing-metrics__panel--top">
                                <p class="fi-marketing-branch-insights__eyebrow fi-client-marketing-metrics__eyebrow">Sucursal seleccionada</p>
                                <p class="fi-marketing-branch-insights__branch-name">
                                    {{ $detail['name'] }}
                                </p>
                                <div class="fi-marketing-branch-insights__highlight-box fi-client-marketing-metrics__meta">
                                    <p class="fi-marketing-branch-insights__highlight-label fi-client-marketing-metrics__meta-label">
                                        Producto más vendido (unidades)
                                    </p>
                                    <p class="fi-marketing-branch-insights__highlight-value fi-client-marketing-metrics__meta-value">
                                        {{ $detail['top_product'] }}
                                    </p>
                                </div>
                            </div>

                            <div class="fi-marketing-branch-insights__panel fi-marketing-branch-insights__panel--clients fi-client-marketing-metrics__panel fi-client-marketing-metrics__panel--top">
                                <h4 class="fi-marketing-branch-insights__panel-title fi-client-marketing-metrics__panel-title">Top 10 clientes por compras</h4>
                                <p class="fi-marketing-branch-insights__panel-sub">
                                    Frecuencia en esta sucursal
                                </p>
                                @if ($detail['top_clients']->isEmpty())
                                    <p class="fi-marketing-branch-insights__muted">Sin datos para esta sucursal.</p>
                                @else
                                    <ol class="fi-marketing-branch-insights__client-list">
                                        @foreach ($detail['top_clients'] as $i => $c)
                                            <li class="fi-marketing-branch-insights__client-item">
                                                <span class="fi-marketing-branch-insights__client-left">
                                                    <span class="fi-marketing-branch-insights__client-rank fi-client-marketing-metrics__top-rank">{{ $i + 1 }}</span>
                                                    <span class="fi-marketing-branch-insights__client-name fi-client-marketing-metrics__top-name">{{ $c->client_name }}</span>
                                                </span>
                                                <span class="fi-marketing-branch-insights__client-count fi-client-marketing-metrics__top-qty">
                                                    {{ $c->purchase_count }} {{ $c->purchase_count === 1 ? 'compra' : 'compras' }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            </div>
                        @else
                            <div class="fi-marketing-branch-insights__placeholder">
                                <p class="fi-marketing-branch-insights__placeholder-text">
                                    Elige una <strong>sucursal</strong> en el listado o en el selector para ver el
                                    <strong>producto más vendido</strong> y el <strong>top 10 de clientes</strong>.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
