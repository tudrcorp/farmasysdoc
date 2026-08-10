<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de pago CxP {{ $accounts_payable->supplier_invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 12mm 10mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5pt;
            color: #1a1a1a;
            margin: 0;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0e5c5f;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header img {
            max-height: 120px;
            width: auto;
            margin-bottom: 6px;
        }
        h1 {
            font-size: 13pt;
            color: #0e5c5f;
            margin: 8px 0 4px 0;
        }
        .meta { font-size: 7.5pt; color: #444; margin-bottom: 10px; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            background: #e6f6e8;
            color: #166534;
            font-weight: bold;
            font-size: 8pt;
        }
        .highlight {
            background: #f0fafb;
            border: 1px solid #0e5c5f;
            border-radius: 4px;
            padding: 10px 12px;
            margin: 10px 0 14px 0;
        }
        .highlight-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .highlight-grid th {
            text-align: left;
            font-size: 7pt;
            text-transform: uppercase;
            color: #0e5c5f;
            padding: 4px 8px 2px 0;
            vertical-align: bottom;
            width: 25%;
        }
        .highlight-grid td {
            font-size: 11pt;
            font-weight: bold;
            padding: 0 8px 8px 0;
            vertical-align: top;
        }
        table.info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8pt;
        }
        table.info th, table.info td {
            border: 1px solid #ddd;
            padding: 5px 8px;
            text-align: left;
        }
        table.info th { background: #f7f7f7; width: 28%; }
        h2 {
            font-size: 10pt;
            color: #0e5c5f;
            margin: 16px 0 8px 0;
            border-bottom: 1px solid #0e5c5f;
            padding-bottom: 3px;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            margin-bottom: 8px;
        }
        table.lines th, table.lines td {
            border: 1px solid #ccc;
            padding: 4px 5px;
            vertical-align: top;
        }
        table.lines th {
            background: #f0fafb;
            font-weight: bold;
        }
        .num { text-align: right; }
        .muted { color: #666; font-size: 7pt; }
        .summary {
            margin-top: 12px;
            padding: 10px 12px;
            background: #f0fafb;
            font-size: 8.5pt;
            border: 1px solid #0e5c5f;
        }
        .summary table { width: 100%; border-collapse: collapse; }
        .summary td { padding: 3px 0; }
        .summary td:last-child { text-align: right; font-weight: bold; }
        .summary .total-row td {
            padding-top: 8px;
            border-top: 1px solid #0e5c5f;
            font-size: 10pt;
            color: #0e5c5f;
        }
        .footer {
            margin-top: 18px;
            font-size: 6.5pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }
        .empty {
            padding: 12px;
            text-align: center;
            color: #666;
            border: 1px dashed #ccc;
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        @if (! empty($pdf_logo_data_uri))
            <img src="{{ $pdf_logo_data_uri }}" alt="Farmadoc">
        @endif
        <h1>Reporte detallado de pago</h1>
        <div class="meta">
            Cuenta por pagar · Factura {{ $accounts_payable->supplier_invoice_number ?: '—' }}
            · Ref. {{ $pdf_document_ref ?? '—' }}
        </div>
        <span class="badge">{{ $status_label }}</span>
    </div>

    <div class="highlight">
        <table class="highlight-grid">
            <tr>
                <th>Proveedor</th>
                <th>RIF</th>
                <th>Total a pagar</th>
                <th>Fecha de pago</th>
            </tr>
            <tr>
                <td>{{ $accounts_payable->supplier_name ?: '—' }}</td>
                <td>{{ $accounts_payable->supplier_tax_id ?: '—' }}</td>
                <td>Bs {{ number_format($amount_payable_ves, 2, ',', '.') }}</td>
                <td>{{ $accounts_payable->paid_at?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <h2>Datos del documento</h2>
    <table class="info">
        <tr>
            <th>Sucursal</th>
            <td>{{ $branch_name }}</td>
            <th>Nº orden de compra</th>
            <td>{{ $purchase_number ?: '—' }}</td>
        </tr>
        <tr>
            <th>Nº factura</th>
            <td>{{ $accounts_payable->supplier_invoice_number ?: '—' }}</td>
            <th>Nº control</th>
            <td>{{ $accounts_payable->supplier_control_number ?: '—' }}</td>
        </tr>
        <tr>
            <th>Emisión</th>
            <td>{{ $accounts_payable->issued_at?->format('d/m/Y') ?? '—' }}</td>
            <th>Vencimiento</th>
            <td>{{ $accounts_payable->due_at?->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <th>Referencia de pago</th>
            <td colspan="3">{{ $accounts_payable->payment_reference ?: '—' }}</td>
        </tr>
        <tr>
            <th>Comprobante adjunto</th>
            <td colspan="3">{{ $has_payment_proof ? 'Sí' : 'No' }}</td>
        </tr>
    </table>

    <h2>Montos e impuestos</h2>
    <table class="info">
        <tr>
            <th>Total compra (USD)</th>
            <td>{{ number_format((float) $accounts_payable->purchase_total_usd, 2, ',', '.') }} USD</td>
            <th>Total factura (Bs, emisión)</th>
            <td>Bs {{ number_format((float) $accounts_payable->purchase_total_ves_at_issue, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <th>IVA causado</th>
            <td>
                @if ($tax_snapshot->taxCausedVes !== null)
                    Bs {{ number_format($tax_snapshot->taxCausedVes, 2, ',', '.') }}
                @else
                    —
                @endif
            </td>
            <th>% retención SENIAT</th>
            <td>
                @if ($tax_snapshot->retentionPercent !== null)
                    {{ number_format($tax_snapshot->retentionPercent, 0, ',', '.') }}%
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <th>Impuesto retenido</th>
            <td>
                @if ($tax_snapshot->taxRetainedVes !== null)
                    Bs {{ number_format($tax_snapshot->taxRetainedVes, 2, ',', '.') }}
                @else
                    Sin retención
                @endif
            </td>
            <th>Total a pagar (Bs)</th>
            <td><strong>Bs {{ number_format($amount_payable_ves, 2, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <th>Principal pendiente (USD)</th>
            <td>{{ number_format((float) ($accounts_payable->remaining_principal_usd ?? 0), 2, ',', '.') }} USD</td>
            <th>Saldo actual (Bs)</th>
            <td>Bs {{ number_format((float) $accounts_payable->current_balance_ves, 2, ',', '.') }}</td>
        </tr>
    </table>

    <h2>Movimientos de pago</h2>
    @if ($payments->isEmpty())
        <div class="empty">No hay movimientos de pago registrados en el histórico de compras para esta cuenta.</div>
    @else
        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 14%;">Fecha</th>
                    <th style="width: 16%;">Método</th>
                    <th style="width: 14%;">Forma</th>
                    <th class="num" style="width: 12%;">USD</th>
                    <th class="num" style="width: 14%;">Bs</th>
                    <th class="num" style="width: 10%;">Tasa BCV</th>
                    <th style="width: 20%;">Referencia / notas</th>
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
                            @if ($payment['bcv_rate'] !== null)
                                {{ number_format($payment['bcv_rate'], 4, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            {{ $payment['payment_reference'] ?: '—' }}
                            @if ($payment['notes'])
                                <div class="muted">{{ $payment['notes'] }}</div>
                            @endif
                            @if ($payment['created_by'])
                                <div class="muted">Registró: {{ $payment['created_by'] }}</div>
                            @endif
                            @if ($payment['retention_voucher_number'] || $payment['retention_amount_ves'] !== null)
                                <div class="muted">
                                    Retención
                                    @if ($payment['retention_voucher_number'])
                                        Nº {{ $payment['retention_voucher_number'] }}
                                    @endif
                                    @if ($payment['retention_amount_ves'] !== null)
                                        · Bs {{ number_format($payment['retention_amount_ves'], 2, ',', '.') }}
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <table>
                <tr>
                    <td>Total pagado (USD)</td>
                    <td>{{ number_format($total_paid_usd, 2, ',', '.') }} USD</td>
                </tr>
                <tr class="total-row">
                    <td>Total pagado (Bs)</td>
                    <td>Bs {{ number_format($total_paid_ves, 2, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    @endif

    @if (filled($accounts_payable->notes))
        <h2>Notas de la cuenta</h2>
        <p>{{ $accounts_payable->notes }}</p>
    @endif

    <div class="footer">
        Generado el {{ $generated_at }} por {{ $generated_by }}.
        Documento de respaldo interno · Farmadoc®.
    </div>
</body>
</html>
