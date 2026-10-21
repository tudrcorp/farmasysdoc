@php
    use App\Services\Reports\FinancialReportBuilder;

    /** @var array<string, mixed>|null $report */
    $report = $report ?? null;
    /** @var array<int, string> $branchOptions */
    $branchOptions = $branchOptions ?? [];
@endphp

<x-filament-panels::page>
    <div class="farmadoc-financial-report">
        <section class="farmadoc-financial-report__filters">
            <div class="farmadoc-financial-report__filters-grid farmadoc-financial-report__filters-grid--three">
                <div class="farmadoc-financial-report__field">
                    <label for="financial-report-from" class="farmadoc-financial-report__label">Desde</label>
                    <input
                        id="financial-report-from"
                        type="date"
                        class="farmadoc-financial-report__input"
                        wire:model.live="dateFrom"
                        wire:change="refreshReport"
                    />
                </div>
                <div class="farmadoc-financial-report__field">
                    <label for="financial-report-until" class="farmadoc-financial-report__label">Hasta</label>
                    <input
                        id="financial-report-until"
                        type="date"
                        class="farmadoc-financial-report__input"
                        wire:model.live="dateUntil"
                        wire:change="refreshReport"
                    />
                </div>
                <div class="farmadoc-financial-report__field">
                    <label for="financial-report-branch" class="farmadoc-financial-report__label">Sucursal</label>
                    <select
                        id="financial-report-branch"
                        class="farmadoc-financial-report__input"
                        wire:model.live="branchId"
                    >
                        <option value="">Todas las sucursales</option>
                        @foreach ($branchOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="farmadoc-financial-report__presets" role="group" aria-label="Atajos de período">
                @foreach ($datePresets as $preset)
                    <button
                        type="button"
                        class="farmadoc-financial-report__preset"
                        wire:click="applyPreset('{{ $preset['desde'] }}', '{{ $preset['hasta'] }}')"
                    >
                        {{ $preset['label'] }}
                    </button>
                @endforeach
            </div>
        </section>

        @if (is_array($report))
            @php
                $income = $report['income'] ?? [];
                $expenses = $report['expenses'] ?? [];
                $net = $report['net'] ?? ['usd' => 0, 'ves' => 0];
                $netCash = $report['net_cash'] ?? ['usd' => 0, 'ves' => 0];
                $periodLabel = $report['period']['label'] ?? '';
                $branchLabel = $report['filters']['branch_label'] ?? 'Todas las sucursales';
                $usdColLabel = $report['columns']['usd_label'] ?? 'USD';
                $vesColLabel = $report['columns']['ves_label'] ?? 'Bs.';
                $paymentChannels = $income['payment_channels'] ?? [];
                $byBranch = $report['by_branch'] ?? [];
                $seniatRow = $expenses['seniat_retentions'] ?? [];
            @endphp

            <p class="farmadoc-financial-report__period">
                Período: <strong>{{ $periodLabel }}</strong>
                · Sucursal: <strong>{{ $branchLabel }}</strong>
            </p>

            <div class="farmadoc-financial-report__summary farmadoc-financial-report__summary--four">
                <article class="farmadoc-financial-report__summary-card farmadoc-financial-report__summary-card--income">
                    <span class="farmadoc-financial-report__summary-label">Ingresos facturados</span>
                    <span class="farmadoc-financial-report__summary-value">{{ FinancialReportBuilder::formatUsd((float) ($income['total']['usd'] ?? 0)) }}</span>
                    <span class="farmadoc-financial-report__summary-sub">{{ FinancialReportBuilder::formatVes((float) ($income['total']['ves'] ?? 0)) }} cobrados</span>
                </article>
                <article class="farmadoc-financial-report__summary-card farmadoc-financial-report__summary-card--expense">
                    <span class="farmadoc-financial-report__summary-label">Egresos (devengado)</span>
                    <span class="farmadoc-financial-report__summary-value">{{ FinancialReportBuilder::formatUsd((float) ($expenses['total_accrual']['usd'] ?? 0)) }}</span>
                    <span class="farmadoc-financial-report__summary-sub">{{ FinancialReportBuilder::formatVes((float) ($expenses['total_accrual']['ves'] ?? 0)) }}</span>
                </article>
                <article class="farmadoc-financial-report__summary-card farmadoc-financial-report__summary-card--net">
                    <span class="farmadoc-financial-report__summary-label">{{ $net['label'] ?? 'Resultado neto' }}</span>
                    <span class="farmadoc-financial-report__summary-value">{{ FinancialReportBuilder::formatUsd((float) ($net['usd'] ?? 0)) }}</span>
                    <span class="farmadoc-financial-report__summary-sub">{{ FinancialReportBuilder::formatVes((float) ($net['ves'] ?? 0)) }}</span>
                </article>
                <article class="farmadoc-financial-report__summary-card farmadoc-financial-report__summary-card--cash">
                    <span class="farmadoc-financial-report__summary-label">{{ $netCash['label'] ?? 'Flujo de caja' }}</span>
                    <span class="farmadoc-financial-report__summary-value">{{ FinancialReportBuilder::formatVes((float) ($netCash['ves'] ?? 0)) }}</span>
                    <span class="farmadoc-financial-report__summary-sub">Cobros − desembolsos del período</span>
                </article>
            </div>

            <p class="farmadoc-financial-report__column-legend">
                <strong>{{ $usdColLabel }}</strong> · <strong>{{ $vesColLabel }}</strong>
            </p>

            <div class="farmadoc-financial-report__columns">
                <section class="farmadoc-financial-report__section">
                    <header class="farmadoc-financial-report__section-header farmadoc-financial-report__section-header--income">
                        <h3>Ingresos</h3>
                    </header>
                    <div class="farmadoc-financial-report__rows">
                        @foreach (['counter_sales', 'corporate_sales', 'accounts_receivable'] as $key)
                            @php $row = $income[$key] ?? []; @endphp
                            <div class="farmadoc-financial-report__row">
                                <div class="farmadoc-financial-report__row-main">
                                    <span class="farmadoc-financial-report__row-title">{{ $row['label'] ?? '' }}</span>
                                    <span class="farmadoc-financial-report__row-meta">{{ $row['count'] ?? 0 }} operaciones · {{ $row['meta'] ?? '' }}</span>
                                    @if (! empty($row['drill_down']) && (int) ($row['count'] ?? 0) > 0)
                                        <a href="{{ $row['drill_down'] }}" class="farmadoc-financial-report__drill-link" target="_blank" rel="noopener">Ver detalle →</a>
                                    @endif
                                </div>
                                <div class="farmadoc-financial-report__row-amounts">
                                    <span>{{ FinancialReportBuilder::formatUsd((float) ($row['usd'] ?? 0)) }}</span>
                                    <span class="farmadoc-financial-report__row-ves">{{ FinancialReportBuilder::formatVes((float) ($row['ves'] ?? 0)) }}</span>
                                </div>
                            </div>
                        @endforeach

                        @if (count($paymentChannels) > 0)
                            <p class="farmadoc-financial-report__group-label">Cobros por método (mostrador)</p>
                            @foreach ($paymentChannels as $channel)
                                <div class="farmadoc-financial-report__row farmadoc-financial-report__row--nested">
                                    <div class="farmadoc-financial-report__row-main">
                                        <span class="farmadoc-financial-report__row-title">{{ $channel['label'] ?? '' }}</span>
                                        <span class="farmadoc-financial-report__row-meta">{{ $channel['count'] ?? 0 }} ventas</span>
                                    </div>
                                    <div class="farmadoc-financial-report__row-amounts">
                                        <span>{{ FinancialReportBuilder::formatUsd((float) ($channel['payment_usd'] ?? 0)) }}</span>
                                        <span class="farmadoc-financial-report__row-ves">{{ FinancialReportBuilder::formatVes((float) ($channel['payment_ves'] ?? 0)) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        <div class="farmadoc-financial-report__row farmadoc-financial-report__row--total">
                            <div class="farmadoc-financial-report__row-main">
                                <span class="farmadoc-financial-report__row-title">Total ingresos</span>
                            </div>
                            <div class="farmadoc-financial-report__row-amounts">
                                <span>{{ FinancialReportBuilder::formatUsd((float) ($income['total']['usd'] ?? 0)) }}</span>
                                <span class="farmadoc-financial-report__row-ves">{{ FinancialReportBuilder::formatVes((float) ($income['total']['ves'] ?? 0)) }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="farmadoc-financial-report__section">
                    <header class="farmadoc-financial-report__section-header farmadoc-financial-report__section-header--expense">
                        <h3>Egresos</h3>
                    </header>
                    <div class="farmadoc-financial-report__rows">
                        @php $purchaseRow = $expenses['purchases'] ?? []; @endphp
                        <div class="farmadoc-financial-report__row">
                            <div class="farmadoc-financial-report__row-main">
                                <span class="farmadoc-financial-report__row-title">{{ $purchaseRow['label'] ?? 'Compras recibidas' }}</span>
                                <span class="farmadoc-financial-report__row-meta">{{ $purchaseRow['count'] ?? 0 }} facturas · {{ $purchaseRow['meta'] ?? '' }}</span>
                                @if (! empty($purchaseRow['drill_down']) && (int) ($purchaseRow['count'] ?? 0) > 0)
                                    <a href="{{ $purchaseRow['drill_down'] }}" class="farmadoc-financial-report__drill-link" target="_blank" rel="noopener">Ver detalle →</a>
                                @endif
                            </div>
                            <div class="farmadoc-financial-report__row-amounts">
                                <span>{{ FinancialReportBuilder::formatUsd((float) ($purchaseRow['usd'] ?? 0)) }}</span>
                                <span class="farmadoc-financial-report__row-ves">{{ FinancialReportBuilder::formatVes((float) ($purchaseRow['ves'] ?? 0)) }}</span>
                            </div>
                        </div>

                        <p class="farmadoc-financial-report__group-label">Gastos fijos</p>

                        @php $payrollRow = $expenses['fixed']['payroll'] ?? []; @endphp
                        <div class="farmadoc-financial-report__row farmadoc-financial-report__row--nested">
                            <div class="farmadoc-financial-report__row-main">
                                <span class="farmadoc-financial-report__row-title">{{ $payrollRow['label'] ?? 'Sueldos y salarios (nómina)' }}</span>
                                <span class="farmadoc-financial-report__row-meta">{{ $payrollRow['count'] ?? 0 }} registros · {{ $payrollRow['meta'] ?? '' }}</span>
                                @if (! empty($payrollRow['drill_down']) && (int) ($payrollRow['count'] ?? 0) > 0)
                                    <a href="{{ $payrollRow['drill_down'] }}" class="farmadoc-financial-report__drill-link" target="_blank" rel="noopener">Ver detalle →</a>
                                @endif
                            </div>
                            <div class="farmadoc-financial-report__row-amounts">
                                <span>{{ FinancialReportBuilder::formatUsd((float) ($payrollRow['usd'] ?? 0)) }}</span>
                                <span class="farmadoc-financial-report__row-ves">{{ FinancialReportBuilder::formatVes((float) ($payrollRow['ves'] ?? 0)) }}</span>
                            </div>
                        </div>

                        @foreach (($expenses['fixed']['services']['by_category'] ?? []) as $categoryRow)
                            <div class="farmadoc-financial-report__row farmadoc-financial-report__row--nested">
                                <div class="farmadoc-financial-report__row-main">
                                    <span class="farmadoc-financial-report__row-title">{{ $categoryRow['label'] ?? '' }}</span>
                                    <span class="farmadoc-financial-report__row-meta">{{ $categoryRow['count'] ?? 0 }} registros · gasto fijo</span>
                                </div>
                                <div class="farmadoc-financial-report__row-amounts">
                                    <span>{{ FinancialReportBuilder::formatUsd((float) ($categoryRow['usd'] ?? 0)) }}</span>
                                    <span class="farmadoc-financial-report__row-ves">{{ FinancialReportBuilder::formatVes((float) ($categoryRow['ves'] ?? 0)) }}</span>
                                </div>
                            </div>
                        @endforeach

                        <p class="farmadoc-financial-report__group-label">Gastos variables</p>

                        @php $pettyRow = $expenses['variable']['petty_cash'] ?? []; @endphp
                        <div class="farmadoc-financial-report__row farmadoc-financial-report__row--nested">
                            <div class="farmadoc-financial-report__row-main">
                                <span class="farmadoc-financial-report__row-title">{{ $pettyRow['label'] ?? 'Caja chica' }}</span>
                                <span class="farmadoc-financial-report__row-meta">{{ $pettyRow['count'] ?? 0 }} {{ ((int) ($pettyRow['count'] ?? 0)) === 1 ? 'aprobación' : 'aprobaciones' }}@if (! empty($pettyRow['meta'])) · {{ $pettyRow['meta'] }}@endif</span>
                                @if (! empty($pettyRow['drill_down']) && (int) ($pettyRow['count'] ?? 0) > 0)
                                    <a href="{{ $pettyRow['drill_down'] }}" class="farmadoc-financial-report__drill-link" target="_blank" rel="noopener">Ver detalle →</a>
                                @endif
                            </div>
                            <div class="farmadoc-financial-report__row-amounts">
                                <span>{{ FinancialReportBuilder::formatUsd((float) ($pettyRow['usd'] ?? 0)) }}</span>
                                <span class="farmadoc-financial-report__row-ves">{{ FinancialReportBuilder::formatVes((float) ($pettyRow['ves'] ?? 0)) }}</span>
                            </div>
                        </div>

                        <div class="farmadoc-financial-report__row farmadoc-financial-report__row--total">
                            <div class="farmadoc-financial-report__row-main">
                                <span class="farmadoc-financial-report__row-title">{{ $expenses['total_accrual']['label'] ?? 'Total egresos (devengado)' }}</span>
                            </div>
                            <div class="farmadoc-financial-report__row-amounts">
                                <span>{{ FinancialReportBuilder::formatUsd((float) ($expenses['total_accrual']['usd'] ?? 0)) }}</span>
                                <span class="farmadoc-financial-report__row-ves">{{ FinancialReportBuilder::formatVes((float) ($expenses['total_accrual']['ves'] ?? 0)) }}</span>
                            </div>
                        </div>

                        <p class="farmadoc-financial-report__group-label">Fiscal / flujo (no sumado arriba)</p>

                        <div class="farmadoc-financial-report__row farmadoc-financial-report__row--nested farmadoc-financial-report__row--fiscal">
                            <div class="farmadoc-financial-report__row-main">
                                <span class="farmadoc-financial-report__row-title">{{ $seniatRow['label'] ?? 'Retenciones IVA (SENIAT)' }}</span>
                                <span class="farmadoc-financial-report__row-meta">{{ $seniatRow['count'] ?? 0 }} comprobantes · {{ $seniatRow['meta'] ?? '' }}</span>
                                @if (! empty($seniatRow['drill_down']) && (int) ($seniatRow['count'] ?? 0) > 0)
                                    <a href="{{ $seniatRow['drill_down'] }}" class="farmadoc-financial-report__drill-link" target="_blank" rel="noopener">Ver retenciones →</a>
                                @endif
                            </div>
                            <div class="farmadoc-financial-report__row-amounts">
                                <span>{{ FinancialReportBuilder::formatUsd((float) ($seniatRow['usd'] ?? 0)) }}</span>
                                <span class="farmadoc-financial-report__row-ves">{{ FinancialReportBuilder::formatVes((float) ($seniatRow['ves'] ?? 0)) }}</span>
                            </div>
                        </div>

                        @php $cxpRow = $expenses['accounts_payable'] ?? []; @endphp
                        <div class="farmadoc-financial-report__row farmadoc-financial-report__row--nested farmadoc-financial-report__row--cash-flow">
                            <div class="farmadoc-financial-report__row-main">
                                <span class="farmadoc-financial-report__row-title">{{ $cxpRow['label'] ?? 'CxP pagadas' }}</span>
                                <span class="farmadoc-financial-report__row-meta">{{ $cxpRow['count'] ?? 0 }} pagos · {{ $cxpRow['meta'] ?? '' }}</span>
                                @if (! empty($cxpRow['drill_down']) && (int) ($cxpRow['count'] ?? 0) > 0)
                                    <a href="{{ $cxpRow['drill_down'] }}" class="farmadoc-financial-report__drill-link" target="_blank" rel="noopener">Ver detalle →</a>
                                @endif
                            </div>
                            <div class="farmadoc-financial-report__row-amounts">
                                <span>{{ FinancialReportBuilder::formatUsd((float) ($cxpRow['usd'] ?? 0)) }}</span>
                                <span class="farmadoc-financial-report__row-ves">{{ FinancialReportBuilder::formatVes((float) ($cxpRow['ves'] ?? 0)) }}</span>
                            </div>
                        </div>

                        <div class="farmadoc-financial-report__row farmadoc-financial-report__row--subtotal">
                            <div class="farmadoc-financial-report__row-main">
                                <span class="farmadoc-financial-report__row-title">{{ $expenses['total_cash']['label'] ?? 'Total desembolsos' }}</span>
                            </div>
                            <div class="farmadoc-financial-report__row-amounts">
                                <span>{{ FinancialReportBuilder::formatUsd((float) ($expenses['total_cash']['usd'] ?? 0)) }}</span>
                                <span class="farmadoc-financial-report__row-ves">{{ FinancialReportBuilder::formatVes((float) ($expenses['total_cash']['ves'] ?? 0)) }}</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            @if (count($byBranch) > 0)
                <section class="farmadoc-financial-report__table-section">
                    <header class="farmadoc-financial-report__table-header">
                        <h3>Resultado por sucursal</h3>
                    </header>
                    <div class="farmadoc-financial-report__table-wrap">
                        <table class="farmadoc-financial-report__table">
                            <thead>
                                <tr>
                                    <th>Sucursal</th>
                                    <th class="farmadoc-financial-report__th-num">Ingresos USD</th>
                                    <th class="farmadoc-financial-report__th-num">Ingresos Bs.</th>
                                    <th class="farmadoc-financial-report__th-num">Egresos USD</th>
                                    <th class="farmadoc-financial-report__th-num">Egresos Bs.</th>
                                    <th class="farmadoc-financial-report__th-num">Neto USD</th>
                                    <th class="farmadoc-financial-report__th-num">Neto Bs.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byBranch as $branchRow)
                                    <tr>
                                        <td>{{ $branchRow['branch'] ?? '' }}</td>
                                        <td class="farmadoc-financial-report__td-num">{{ FinancialReportBuilder::formatUsd((float) ($branchRow['income_usd'] ?? 0)) }}</td>
                                        <td class="farmadoc-financial-report__td-num">{{ FinancialReportBuilder::formatVes((float) ($branchRow['income_ves'] ?? 0)) }}</td>
                                        <td class="farmadoc-financial-report__td-num">{{ FinancialReportBuilder::formatUsd((float) ($branchRow['expense_usd'] ?? 0)) }}</td>
                                        <td class="farmadoc-financial-report__td-num">{{ FinancialReportBuilder::formatVes((float) ($branchRow['expense_ves'] ?? 0)) }}</td>
                                        <td class="farmadoc-financial-report__td-num">{{ FinancialReportBuilder::formatUsd((float) ($branchRow['net_usd'] ?? 0)) }}</td>
                                        <td class="farmadoc-financial-report__td-num">{{ FinancialReportBuilder::formatVes((float) ($branchRow['net_ves'] ?? 0)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @php $fixedItems = $report['fixed_service_items'] ?? []; @endphp
            @if (count($fixedItems) > 0)
                <section class="farmadoc-financial-report__table-section">
                    <header class="farmadoc-financial-report__table-header">
                        <h3>Servicios fijos del período</h3>
                    </header>
                    <div class="farmadoc-financial-report__table-wrap">
                        <table class="farmadoc-financial-report__table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Categoría</th>
                                    <th>Descripción</th>
                                    <th>Sucursal</th>
                                    <th class="farmadoc-financial-report__th-num">USD</th>
                                    <th class="farmadoc-financial-report__th-num">Bs.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($fixedItems as $item)
                                    <tr>
                                        <td>{{ $item['paid_at'] ?? '' }}</td>
                                        <td>{{ $item['category'] ?? '' }}</td>
                                        <td>{{ $item['description'] ?? '' }}</td>
                                        <td>{{ $item['branch'] ?? '' }}</td>
                                        <td class="farmadoc-financial-report__td-num">{{ FinancialReportBuilder::formatUsd((float) ($item['usd'] ?? 0)) }}</td>
                                        <td class="farmadoc-financial-report__td-num">{{ FinancialReportBuilder::formatVes((float) ($item['ves'] ?? 0)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <p class="farmadoc-financial-report__footnote">
                Los ingresos en USD reflejan el total facturado (incluye crédito, Cashea y ventas en bolívares).
                La columna Bs. en ingresos muestra el cobro físico en caja. Compras y CxP no se suman juntas.
                Las retenciones SENIAT son referencia fiscal por comprobante emitido; no se suman al total devengado.
            </p>
        @endif
    </div>
</x-filament-panels::page>
