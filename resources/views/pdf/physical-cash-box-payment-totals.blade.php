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

<h2>Totales por tipo de pago</h2>
<p class="muted" style="margin: 0 0 8px 0;">Agrupación por forma de pago registrada en cada venta completada del período.</p>
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

<p class="footer-note">Reporte automático al cierre de caja física.</p>
</body>
</html>
