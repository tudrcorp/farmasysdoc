<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte masivo de pagos CxP</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 10mm 8mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 7.5pt;
            color: #1a1a1a;
            margin: 0;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0e5c5f;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header img {
            max-height: 120px;
            width: auto;
            margin-bottom: 6px;
        }
        h1 {
            font-size: 12pt;
            color: #0e5c5f;
            margin: 6px 0 3px 0;
        }
        .meta { font-size: 7pt; color: #444; }
        table.main {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.main th, table.main td {
            border: 1px solid #ccc;
            padding: 3px 5px;
            vertical-align: top;
        }
        table.main th {
            background: #f0fafb;
            font-weight: bold;
            text-align: left;
            font-size: 7pt;
        }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .summary {
            margin-top: 10px;
            padding: 8px 10px;
            background: #f0fafb;
            border: 1px solid #0e5c5f;
            font-size: 8pt;
        }
        .summary table { width: 100%; border-collapse: collapse; }
        .summary td { padding: 2px 6px; }
        .summary td.label { text-align: right; color: #0e5c5f; width: 20%; }
        .summary td.value { font-weight: bold; width: 13%; }
        .footer {
            margin-top: 12px;
            font-size: 6.5pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
        .muted { color: #666; font-size: 6.5pt; }
        h2 {
            font-size: 9.5pt;
            color: #0e5c5f;
            margin: 14px 0 6px 0;
            border-bottom: 1px solid #0e5c5f;
            padding-bottom: 2px;
        }
        .invoice-block {
            margin: 8px 0 12px 0;
            border: 1px solid #cfe6e7;
            padding: 8px 10px;
            page-break-inside: avoid;
        }
        .invoice-block h3 {
            font-size: 8.5pt;
            color: #0e5c5f;
            margin: 0 0 6px 0;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        table.lines th, table.lines td {
            border: 1px solid #ccc;
            padding: 3px 4px;
        }
        table.lines th {
            background: #f0fafb;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        @if (! empty($pdf_logo_data_uri))
            <img src="{{ $pdf_logo_data_uri }}" alt="Farmadoc">
        @endif
        <h1>Reporte masivo de pagos — Cuentas por pagar</h1>
        <div class="meta">
            {{ $count }} factura(s) cancelada(s)
            · Método: {{ $payment_method ?? '—' }}
            · Forma: {{ $payment_form ?? '—' }}
            · Fecha: {{ $paid_at ?? '—' }}
            · Generado: {{ $generated_at }}
            · Usuario: {{ $generated_by }}
            · Ref. {{ $pdf_document_ref ?? '—' }}
        </div>
    </div>

    <table class="main">
        <thead>
            <tr>
                <th style="width: 3%;">#</th>
                <th style="width: 10%;">Fecha pago</th>
                <th style="width: 16%;">Proveedor</th>
                <th style="width: 8%;">RIF</th>
                <th style="width: 8%;">Nº factura</th>
                <th style="width: 8%;">Nº compra</th>
                <th style="width: 10%;">Sucursal</th>
                <th class="num" style="width: 9%;">Total a pagar</th>
                <th class="num" style="width: 8%;">Retenido</th>
                <th class="num" style="width: 8%;">Pagado USD</th>
                <th class="num" style="width: 9%;">Pagado Bs</th>
                <th style="width: 8%;">Referencia</th>
                <th style="width: 9%;">Método</th>
                <th class="center" style="width: 5%;">Comp.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $index => $row)
                @php
                    /** @var \App\Models\AccountsPayable $ap */
                    $ap = $row['accounts_payable'];
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row['paid_at'] }}</td>
                    <td>{{ $ap->supplier_name ?: '—' }}</td>
                    <td>{{ $ap->supplier_tax_id ?: '—' }}</td>
                    <td>{{ $ap->supplier_invoice_number ?: '—' }}</td>
                    <td>{{ $row['purchase_number'] ?: '—' }}</td>
                    <td>{{ $row['branch_name'] }}</td>
                    <td class="num">{{ number_format($row['amount_payable_ves'], 2, ',', '.') }}</td>
                    <td class="num">
                        @if ($row['tax_retained_ves'] !== null)
                            {{ number_format($row['tax_retained_ves'], 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">{{ number_format($row['total_paid_usd'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['total_paid_ves'], 2, ',', '.') }}</td>
                    <td>
                        {{ $row['payment_reference'] ?: '—' }}
                        @if (filled($ap->notes))
                            <div class="muted">{{ \Illuminate\Support\Str::limit((string) $ap->notes, 60) }}</div>
                        @endif
                    </td>
                    <td>{{ $row['payment_method'] ?? '—' }}</td>
                    <td class="center">{{ $row['has_payment_proof'] ? 'Sí' : 'No' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <table>
            <tr>
                <td class="label">Registros</td>
                <td class="value">{{ $count }}</td>
                <td class="label">Total a pagar</td>
                <td class="value">Bs {{ number_format($total_amount_payable_ves, 2, ',', '.') }}</td>
                <td class="label">Total retenido</td>
                <td class="value">Bs {{ number_format($total_tax_retained_ves, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Total pagado USD</td>
                <td class="value">{{ number_format($total_paid_usd, 2, ',', '.') }} USD</td>
                <td class="label">Total pagado Bs</td>
                <td class="value">Bs {{ number_format($total_paid_ves, 2, ',', '.') }}</td>
                <td class="label"></td>
                <td class="value"></td>
            </tr>
        </table>
    </div>

    <h2>Detalle de cada factura cancelada</h2>
    @foreach ($rows as $row)
        @php
            /** @var \App\Models\AccountsPayable $ap */
            $ap = $row['accounts_payable'];
            $payments = $row['payments'] ?? collect();
        @endphp
        <div class="invoice-block">
            <h3>
                Factura {{ $ap->supplier_invoice_number ?: '—' }}
                · Control {{ $ap->supplier_control_number ?: '—' }}
                · {{ $ap->supplier_name ?: '—' }}
                · {{ $ap->supplier_tax_id ?: '—' }}
            </h3>
            <div class="muted" style="margin-bottom: 5px;">
                OC {{ $row['purchase_number'] ?: '—' }}
                · {{ $row['branch_name'] }}
                · {{ $row['payment_form'] ?? '—' }}
                @if (! empty($row['bcv_rate']))
                    · BCV {{ number_format((float) $row['bcv_rate'], 4, ',', '.') }}
                @endif
            </div>
            @if ($payments->isEmpty())
                <div class="muted">Sin movimientos de pago en el histórico.</div>
            @else
                <table class="lines">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Método</th>
                            <th>Forma</th>
                            <th class="num">USD</th>
                            <th class="num">Bs</th>
                            <th class="num">Tasa BCV</th>
                            <th>Referencia</th>
                            <th class="num">Retenido Bs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td>{{ $payment['paid_at'] }}</td>
                                <td>{{ $payment['payment_method'] }}</td>
                                <td>{{ $payment['payment_form'] }}</td>
                                <td class="num">{{ number_format($payment['amount_paid_usd'], 2, ',', '.') }}</td>
                                <td class="num">{{ number_format($payment['amount_paid_ves'], 2, ',', '.') }}</td>
                                <td class="num">
                                    {{ $payment['bcv_rate'] !== null ? number_format((float) $payment['bcv_rate'], 4, ',', '.') : '—' }}
                                </td>
                                <td>{{ $payment['payment_reference'] ?: '—' }}</td>
                                <td class="num">
                                    {{ $payment['retention_amount_ves'] !== null ? number_format((float) $payment['retention_amount_ves'], 2, ',', '.') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <div class="footer">
        Documento de respaldo interno · Farmadoc® · Solo incluye cuentas por pagar en estado Pagado.
    </div>
</body>
</html>
