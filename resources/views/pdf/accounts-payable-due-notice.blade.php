@php
    $usd = fn ($v) => '$' . number_format((float) $v, 2, ',', '.');
    $ves = fn ($v) => 'Bs ' . number_format((float) $v, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Facturas de proveedores por pagar · {{ $today->format('d/m/Y') }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #1a1a1a; margin: 0; }
        .header { text-align: center; border-bottom: 2px solid #0e5c5f; padding-bottom: 8px; margin-bottom: 10px; }
        .header img { max-height: 70px; width: auto; margin-bottom: 4px; }
        h1 { font-size: 13pt; color: #0e5c5f; margin: 4px 0 2px; }
        .meta { font-size: 7.5pt; color: #555; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 8px 0 12px; }
        .summary td { border: 1px solid #d1d5db; border-radius: 4px; padding: 6px 8px; vertical-align: top; width: 33%; }
        .summary .label { font-size: 7pt; color: #555; text-transform: uppercase; }
        .summary .value { font-size: 11pt; font-weight: bold; margin-top: 2px; }
        .summary .sub { font-size: 7.5pt; color: #444; }
        .section-title { margin: 14px 0 2px; padding: 5px 8px; color: #fff; font-size: 10pt; font-weight: bold; border-radius: 3px; }
        .tone-warning { background: #b45309; }
        .tone-danger { background: #b91c1c; }
        .tone-overdue { background: #374151; }
        .section-sub { font-size: 7.5pt; color: #555; margin: 0 0 6px; }
        .supplier { margin: 8px 0 2px; padding: 4px 6px; background: #f3f4f6; border-left: 3px solid #0e5c5f; }
        .supplier strong { font-size: 8.5pt; }
        .banks { font-size: 7pt; color: #374151; margin-top: 2px; }
        table.rows { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        table.rows th { background: #e5e7eb; font-size: 7pt; text-align: left; padding: 3px 4px; border-bottom: 1px solid #9ca3af; }
        table.rows td { padding: 3px 4px; border-bottom: 1px solid #e5e7eb; font-size: 7.5pt; }
        .num { text-align: right; white-space: nowrap; }
        .subtotal td { font-weight: bold; background: #f9fafb; border-top: 1px solid #9ca3af; }
        .total td { font-weight: bold; font-size: 8.5pt; background: #ecfeff; border-top: 2px solid #0e5c5f; }
        .days-urgent { color: #b91c1c; font-weight: bold; white-space: nowrap; }
        .footer { margin-top: 14px; font-size: 6.5pt; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        @if ($logo)
            <img src="{{ $logo }}" alt="Farmadoc">
        @endif
        <h1>Facturas de proveedores por pagar</h1>
        <div class="meta">Aviso del {{ $today->format('d/m/Y') }} · generado {{ $generated_at->format('d/m/Y h:i A') }} · saldos en USD (capital pendiente) y Bs (saldo actual indexado)</div>
    </div>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Por vencer (este aviso)</div>
                <div class="value">{{ $upcoming_count }} factura(s)</div>
                <div class="sub">{{ $usd($upcoming_total_usd) }} · {{ $ves($upcoming_total_ves) }}</div>
            </td>
            <td>
                <div class="label">Vencidas sin pagar</div>
                <div class="value">{{ $overdue_count }} factura(s)</div>
                <div class="sub">{{ $usd($overdue_total_usd) }} · {{ $ves($overdue_total_ves) }}</div>
            </td>
            <td>
                <div class="label">Total comprometido</div>
                <div class="value">{{ $usd($upcoming_total_usd + $overdue_total_usd) }}</div>
                <div class="sub">{{ $ves($upcoming_total_ves + $overdue_total_ves) }}</div>
            </td>
        </tr>
    </table>

    @foreach ($sections as $section)
        <div class="section-title tone-{{ $section['tone'] }}">{{ $section['title'] }} · {{ $section['count'] }} factura(s) · {{ $usd($section['total_usd']) }} · {{ $ves($section['total_ves']) }}</div>
        <p class="section-sub">{{ $section['subtitle'] }}</p>

        @foreach ($section['suppliers'] as $supplier)
            <div class="supplier">
                <strong>{{ $supplier['name'] }}</strong>@if ($supplier['tax_id']) · RIF {{ $supplier['tax_id'] }}@endif
                <div class="banks">
                    @forelse ($supplier['bank_accounts'] as $account)
                        {{ $account }}@if (! $loop->last) &nbsp;|&nbsp; @endif
                    @empty
                        Sin cuentas bancarias registradas
                    @endforelse
                </div>
            </div>
            <table class="rows">
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Control</th>
                        <th>Sucursal</th>
                        <th>Emisión</th>
                        <th>Vence</th>
                        <th>Plazo</th>
                        <th class="num">Saldo USD</th>
                        <th class="num">Saldo Bs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($supplier['rows'] as $row)
                        <tr>
                            <td>{{ $row['invoice'] }}</td>
                            <td>{{ $row['control'] }}</td>
                            <td>{{ $row['branch'] }}</td>
                            <td>{{ $row['issued_at'] }}</td>
                            <td>{{ $row['due_at'] }}</td>
                            <td class="{{ $row['days'] <= 1 ? 'days-urgent' : '' }}">{{ $row['days_label'] }}</td>
                            <td class="num">{{ $usd($row['usd']) }}</td>
                            <td class="num">{{ $ves($row['ves']) }}</td>
                        </tr>
                    @endforeach
                    @if (count($supplier['rows']) > 1)
                        <tr class="subtotal">
                            <td colspan="6">Subtotal {{ $supplier['name'] }}</td>
                            <td class="num">{{ $usd($supplier['total_usd']) }}</td>
                            <td class="num">{{ $ves($supplier['total_ves']) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        @endforeach
    @endforeach

    @if (count($overdue_rows) > 0)
        <div class="section-title tone-overdue">Vencidas sin pagar · {{ $overdue_count }} factura(s) · {{ $usd($overdue_total_usd) }} · {{ $ves($overdue_total_ves) }}</div>
        <p class="section-sub">Facturas cuyo vencimiento ya pasó y siguen pendientes.</p>
        <table class="rows">
            <thead>
                <tr>
                    <th>Proveedor</th>
                    <th>Factura</th>
                    <th>Sucursal</th>
                    <th>Vence</th>
                    <th>Atraso</th>
                    <th class="num">Saldo USD</th>
                    <th class="num">Saldo Bs</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($overdue_rows as $row)
                    <tr>
                        <td>{{ $row['supplier'] }}</td>
                        <td>{{ $row['invoice'] }}</td>
                        <td>{{ $row['branch'] }}</td>
                        <td>{{ $row['due_at'] }}</td>
                        <td class="days-urgent">{{ $row['days_label'] }}</td>
                        <td class="num">{{ $usd($row['usd']) }}</td>
                        <td class="num">{{ $ves($row['ves']) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="5">Total vencido</td>
                    <td class="num">{{ $usd($overdue_total_usd) }}</td>
                    <td class="num">{{ $ves($overdue_total_ves) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="footer">Farmadoc · Aviso automático de cuentas por pagar. Los montos en Bs se indexan a la tasa BCV del día de la última actualización.</div>
</body>
</html>
