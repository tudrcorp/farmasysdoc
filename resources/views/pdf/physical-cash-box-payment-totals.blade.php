<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Totales por tipo de pago — {{ $cashier_name }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 14mm 12mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .doc-header {
            width: 100%;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 3px solid #18acb2;
            text-align: center;
        }
        .doc-header-logo {
            margin: 0 0 10px 0;
        }
        .doc-header-logo img {
            max-height: 56px;
            width: auto;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .doc-title {
            font-size: 14pt;
            margin: 0 0 6px 0;
            padding: 0;
            color: #0e5c5f;
            font-weight: bold;
            text-align: center;
        }
        .doc-sub {
            font-size: 8.5pt;
            color: #444;
            line-height: 1.45;
            text-align: center;
        }

        h2 {
            font-size: 11pt;
            margin: 18px 0 8px 0;
            padding: 5px 0 5px 10px;
            color: #0e5c5f;
            border-left: 5px solid #fce422;
            background: #f0fafb;
        }
        .meta {
            font-size: 8.5pt;
            color: #444;
            line-height: 1.45;
            padding: 10px 12px;
            background: #ffffff;
            border: 1px solid #c5e8ea;
            border-radius: 2px;
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background: #ffffff;
        }
        th, td {
            border: 1px solid #9ccfd2;
            padding: 5px 7px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #d9f4f5;
            color: #0a4d50;
            font-weight: bold;
            font-size: 8pt;
        }
        tbody tr:nth-child(even) td {
            background: #fafefe;
        }
        tfoot td {
            background: #fff9e6;
            color: #1a1a1a;
            font-weight: bold;
            border-top: 2px solid #0e949a;
        }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .center { text-align: center; }
        .muted { color: #555; font-size: 8pt; }
        .mismatch { color: #b91c1c; font-weight: bold; }
        .balanced { color: #166534; font-weight: bold; }
        .footer-note {
            margin-top: 20px;
            font-size: 7.5pt;
            color: #444;
            border: 1px solid #c5e8ea;
            background: #f0fafb;
            padding: 10px 12px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="doc-header">
    @if (filled($pdf_logo_data_uri))
        <div class="doc-header-logo">
            <img src="{{ $pdf_logo_data_uri }}" alt="{{ $app_name }}">
        </div>
    @endif
    <p class="doc-title">Cierre de caja física — Totales por tipo de pago</p>
    <p class="doc-sub">{{ $app_name }} · Generado {{ $generated_at }}</p>
</div>

<div class="meta">
    <strong>Sucursal:</strong> {{ $branch_name }}<br>
    <strong>Cajero:</strong> {{ $cashier_name }}<br>
    <strong>Turno:</strong> {{ $opened_at_label }} — {{ $closed_at_label }}
</div>

@php
    $detail = $close_detail;
@endphp

<h2>Resumen del turno</h2>
<table>
    <tbody>
    <tr>
        <td>Total de ventas</td>
        <td class="num">{{ number_format($detail['sale_count'], 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Total ventas USD</td>
        <td class="num">$ {{ number_format($detail['total_usd'], 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Total ventas VES</td>
        <td class="num">Bs. {{ number_format($detail['total_ves'], 2, ',', '.') }}</td>
    </tr>
    </tbody>
</table>

<h2>Detalle de ventas</h2>
<p class="muted" style="margin: 0 0 8px 0;">Solo dinero cobrado en cada moneda. No se convierten bolívares a dólares. Cashea: solo la cuota.</p>
<table>
    <thead>
    <tr>
        <th>Concepto</th>
        <th class="num">Monto</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td><strong>Total Punto de Venta</strong></td>
        <td class="num"><strong>Bs. {{ number_format($detail['punto_venta_ves'], 2, ',', '.') }}</strong></td>
    </tr>
    @forelse ($detail['pos_terminals'] as $terminal)
        <tr>
            <td style="padding-left: 18px;">{{ $terminal['label'] }}</td>
            <td class="num">Bs. {{ number_format($terminal['amount_ves'], 2, ',', '.') }}</td>
        </tr>
    @empty
        <tr>
            <td style="padding-left: 18px;" class="muted">Sin puntos de venta asociados a la sucursal</td>
            <td class="num">Bs. 0,00</td>
        </tr>
    @endforelse
    <tr>
        <td><strong>Total Pago Móvil</strong></td>
        <td class="num"><strong>Bs. {{ number_format($detail['pago_movil_ves'], 2, ',', '.') }}</strong></td>
    </tr>
    <tr>
        <td>Transferencias VES</td>
        <td class="num">Bs. {{ number_format($detail['transfer_ves'] ?? 0, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Transferencias USD</td>
        <td class="num">$ {{ number_format($detail['transfer_usd'] ?? 0, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Efectivo VES</td>
        <td class="num">Bs. {{ number_format($detail['efectivo_ves'] ?? 0, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Efectivo USD</td>
        <td class="num">$ {{ number_format($detail['efectivo_usd'] ?? 0, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td><strong>Total USD cobrado</strong></td>
        <td class="num"><strong>$ {{ number_format($detail['usd_methods_total'], 2, ',', '.') }}</strong></td>
    </tr>
    <tr>
        <td><strong>Total VES cobrado</strong></td>
        <td class="num"><strong>Bs. {{ number_format($detail['ves_methods_total'], 2, ',', '.') }}</strong></td>
    </tr>
    </tbody>
</table>

@php
    $cash = is_array($cash_box_reconciliation ?? null) ? $cash_box_reconciliation : null;
    $pos = is_array($pos_reconciliation ?? null) ? $pos_reconciliation : null;
@endphp

@if ($cash !== null || $pos !== null)
    <h2>Comparación de cierre (declarado vs sistema)</h2>
    <p class="muted" style="margin: 0 0 8px 0;">Faltante y sobrante se marcan en rojo. Cuadrado indica que el cajero y el sistema coinciden.</p>

    @if ($cash !== null)
        <table>
            <thead>
            <tr>
                <th>Efectivo en caja física</th>
                <th class="num">Sistema</th>
                <th class="num">Declarado</th>
                <th class="num">Diferencia</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            @php
                $usdMismatch = abs((float) ($cash['difference_usd'] ?? 0)) >= 0.01;
                $vesMismatch = abs((float) ($cash['difference_ves'] ?? 0)) >= 0.01;
                $usdLabel = $usdMismatch ? (((float) $cash['difference_usd'] > 0) ? 'Sobrante' : 'Faltante') : 'Cuadrado';
                $vesLabel = $vesMismatch ? (((float) $cash['difference_ves'] > 0) ? 'Sobrante' : 'Faltante') : 'Cuadrado';
            @endphp
            <tr>
                <td>Dólares</td>
                <td class="num">$ {{ number_format((float) $cash['expected_usd'], 2, ',', '.') }}</td>
                <td class="num">$ {{ number_format((float) $cash['declared_usd'], 2, ',', '.') }}</td>
                <td class="num {{ $usdMismatch ? 'mismatch' : 'balanced' }}">$ {{ number_format((float) $cash['difference_usd'], 2, ',', '.') }}</td>
                <td class="{{ $usdMismatch ? 'mismatch' : 'balanced' }}">{{ $usdLabel }}</td>
            </tr>
            <tr>
                <td>Bolívares</td>
                <td class="num">Bs. {{ number_format((float) $cash['expected_ves'], 2, ',', '.') }}</td>
                <td class="num">Bs. {{ number_format((float) $cash['declared_ves'], 2, ',', '.') }}</td>
                <td class="num {{ $vesMismatch ? 'mismatch' : 'balanced' }}">Bs. {{ number_format((float) $cash['difference_ves'], 2, ',', '.') }}</td>
                <td class="{{ $vesMismatch ? 'mismatch' : 'balanced' }}">{{ $vesLabel }}</td>
            </tr>
            </tbody>
        </table>
    @endif

    @if ($pos !== null)
        <table>
            <thead>
            <tr>
                <th>Punto de venta (banco)</th>
                <th class="num">Sistema</th>
                <th class="num">Declarado</th>
                <th class="num">Diferencia</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            @forelse (($pos['lines'] ?? []) as $line)
                @php
                    $lineMismatch = abs((float) ($line['difference_ves'] ?? 0)) >= 0.01;
                @endphp
                <tr>
                    <td>{{ $line['bank_label'] }}</td>
                    <td class="num">Bs. {{ number_format((float) $line['system_ves'], 2, ',', '.') }}</td>
                    <td class="num">Bs. {{ number_format((float) $line['declared_ves'], 2, ',', '.') }}</td>
                    <td class="num {{ $lineMismatch ? 'mismatch' : 'balanced' }}">Bs. {{ number_format((float) $line['difference_ves'], 2, ',', '.') }}</td>
                    <td class="{{ $lineMismatch ? 'mismatch' : 'balanced' }}">{{ $line['status_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Sin declaraciones ni cobros de punto de venta en el turno.</td>
                </tr>
            @endforelse
            </tbody>
            <tfoot>
            <tr>
                <td>Total punto de venta</td>
                <td class="num">Bs. {{ number_format((float) ($pos['system_total_ves'] ?? 0), 2, ',', '.') }}</td>
                <td class="num">Bs. {{ number_format((float) ($pos['declared_total_ves'] ?? 0), 2, ',', '.') }}</td>
                @php $posTotalMismatch = abs((float) ($pos['difference_ves'] ?? 0)) >= 0.01; @endphp
                <td class="num {{ $posTotalMismatch ? 'mismatch' : 'balanced' }}">Bs. {{ number_format((float) ($pos['difference_ves'] ?? 0), 2, ',', '.') }}</td>
                <td class="{{ $posTotalMismatch ? 'mismatch' : 'balanced' }}">{{ $posTotalMismatch ? (((float) ($pos['difference_ves'] ?? 0) > 0) ? 'Sobrante' : 'Faltante') : 'Cuadrado' }}</td>
            </tr>
            </tfoot>
        </table>
    @endif
@endif

<h2>Totales por tipo de pago</h2>
            <p class="muted" style="margin: 0 0 8px 0;">Canal real de cobro: Cashea y pago múltiple se agrupan en punto de venta, Zelle, efectivo, etc. USD y VES no se convierten entre sí.</p>
@if (count($payment_breakdown) === 0)
    <p class="muted">Sin operaciones en el período.</p>
@else
    @php
        $pt = $payment_breakdown_totals;
    @endphp
    <table>
        <thead>
        <tr>
            <th>Forma de pago</th>
            <th class="center">Nº ventas</th>
            <th class="num">Cobro USD</th>
            <th class="num">Cobro VES (Bs.)</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($payment_breakdown as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="center">{{ $row['count'] }}</td>
                <td class="num">$ {{ number_format($row['payment_usd'], 2, ',', '.') }}</td>
                <td class="num">Bs. {{ number_format($row['payment_ves'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td>Total (todos los métodos)</td>
            <td class="center">{{ $pt['count'] }}</td>
            <td class="num">$ {{ number_format($pt['payment_usd'], 2, ',', '.') }}</td>
            <td class="num">Bs. {{ number_format($pt['payment_ves'], 2, ',', '.') }}</td>
        </tr>
        </tfoot>
    </table>
@endif

@php
    $cachea = $cachea_detail ?? null;
@endphp
@if (is_array($cachea) && (int) ($cachea['sale_count'] ?? 0) > 0)
    <h2>Cashea — cuotas pagadas</h2>
    <p class="muted" style="margin: 0 0 8px 0;">La cuota ya está incluida en el cobro de arriba. El financiamiento no entra a caja.</p>
    <table>
        <thead>
        <tr>
            <th>Cómo pagó el cliente la cuota</th>
            <th class="center">Nº</th>
            <th class="num">Cuota (USD)</th>
            <th class="num">Cobrado USD</th>
            <th class="num">Cobrado VES</th>
        </tr>
        </thead>
        <tbody>
        @foreach (($cachea['channels'] ?? []) as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="center">{{ $row['count'] }}</td>
                <td class="num">$ {{ number_format($row['cuota_usd'], 2, ',', '.') }}</td>
                <td class="num">$ {{ number_format($row['collected_usd'], 2, ',', '.') }}</td>
                <td class="num">Bs. {{ number_format($row['collected_ves'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td>Total cuotas Cashea</td>
            <td class="center">{{ $cachea['sale_count'] }}</td>
            <td class="num">$ {{ number_format($cachea['cuota_usd'], 2, ',', '.') }}</td>
            <td class="num">$ {{ number_format($cachea['collected_usd'], 2, ',', '.') }}</td>
            <td class="num">Bs. {{ number_format($cachea['collected_ves'], 2, ',', '.') }}</td>
        </tr>
        @if ((float) ($cachea['remainder_usd'] ?? 0) > 0.00001)
            <tr>
                <td colspan="4">Financiado Cashea (no cobrado en caja)</td>
                <td class="num">$ {{ number_format($cachea['remainder_usd'], 2, ',', '.') }}</td>
            </tr>
        @endif
        </tfoot>
    </table>
@endif

<p class="footer-note">Reporte automático al cierre de caja física.</p>
</body>
</html>
