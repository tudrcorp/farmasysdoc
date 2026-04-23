<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compra {{ $purchase->purchase_number }}</title>
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
        .header img { max-height: 48px; }
        h1 {
            font-size: 13pt;
            color: #0e5c5f;
            margin: 8px 0 4px 0;
        }
        .meta { font-size: 7.5pt; color: #444; margin-bottom: 10px; }
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
            width: 16%;
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
        table.info th { background: #f7f7f7; width: 22%; }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
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
        .summary {
            margin-top: 12px;
            padding: 10px 12px;
            background: #f0fafb;
            font-size: 8.5pt;
            border: 1px solid #0e5c5f;
        }
        .summary-title {
            font-size: 9pt;
            font-weight: bold;
            color: #0e5c5f;
            margin: 0 0 8px 0;
            text-align: right;
        }
        .summary table { width: 100%; border-collapse: collapse; }
        .summary table.breakdown { margin-left: auto; width: 78%; }
        .summary table.breakdown th {
            text-align: right;
            font-size: 7pt;
            text-transform: uppercase;
            color: #0e5c5f;
            padding: 4px 8px 4px 0;
            border-bottom: 1px solid #0e5c5f;
        }
        .summary table.breakdown th:last-child { text-align: right; width: 28%; }
        .summary table.breakdown td { padding: 3px 8px 3px 0; vertical-align: top; }
        .summary table.breakdown td:first-child { text-align: right; color: #333; }
        .summary table.breakdown td:last-child {
            text-align: right;
            font-weight: bold;
            white-space: nowrap;
        }
        .summary table.breakdown tr.total-row td {
            padding-top: 8px;
            border-top: 1px solid #0e5c5f;
            font-size: 9.5pt;
        }
        .summary-footnote {
            margin: 10px 0 0 0;
            font-size: 6.8pt;
            color: #555;
            line-height: 1.35;
            text-align: right;
            max-width: 78%;
            margin-left: auto;
        }
        .ref { font-size: 7pt; color: #666; margin-top: 12px; }
    </style>
</head>
<body>
    @php
        $pdfSym = $purchase->documentMoneyPrefix();
    @endphp
    <div class="header">
        @if (! empty($pdf_logo_data_uri))
            <img src="{{ $pdf_logo_data_uri }}" alt="Logo" />
        @endif
        <h1>Documento de compra</h1>
        <div class="meta">
            Orden interna: <strong>{{ $purchase->purchase_number }}</strong>
            · Generado: {{ $generated_at }} · Usuario: {{ $generated_by }}
        </div>
    </div>

    <div class="highlight">
        <table class="highlight-grid">
            <tr>
                <th>Nº factura (proveedor)</th>
                <th>Nº de control</th>
                <th>Proveedor</th>
                <th>Fecha factura</th>
                <th>Vencimiento</th>
                <th>Fecha carga en sistema</th>
            </tr>
            <tr>
                <td>{{ filled($purchase->supplier_invoice_number) ? $purchase->supplier_invoice_number : '—' }}</td>
                <td>{{ filled($purchase->supplier_control_number) ? $purchase->supplier_control_number : '—' }}</td>
                <td>{{ $purchase->supplier ? ($purchase->supplier->trade_name ?: $purchase->supplier->legal_name) : '—' }}</td>
                <td>{{ $purchase->supplier_invoice_date?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $purchase->payment_due_date?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $purchase->registered_in_system_date?->format('d/m/Y') ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <table class="info">
        <tr>
            <th>Sucursal de recepción</th>
            <td>{{ $purchase->branch?->name ?? '—' }}</td>
            <th>Estado</th>
            <td>{{ $purchase->status instanceof \App\Enums\PurchaseStatus ? $purchase->status->label() : (string) $purchase->status }}</td>
        </tr>
        <tr>
            <th>Pago al proveedor</th>
            <td>{{ \App\Support\Purchases\PurchasePaymentStatus::label($purchase->payment_status) }}</td>
            <th>Registró</th>
            <td>{{ filled($purchase->created_by) ? $purchase->created_by : '—' }}</td>
        </tr>
        <tr>
            <th>Moneda del documento</th>
            <td>{{ $purchase->entryCurrency()->value }}</td>
            <th>Tasa oficial (Bs/USD)</th>
            <td>
                @if ($purchase->entryCurrency() === \App\Enums\PurchaseEntryCurrency::VES && filled($purchase->official_usd_ves_rate))
                    {{ number_format((float) $purchase->official_usd_ves_rate, 8, ',', '.') }}
                @else
                    —
                @endif
            </td>
        </tr>
    </table>

    <p style="font-size:9pt;font-weight:bold;color:#0e5c5f;margin:10px 0 4px 0;">Detalle de productos</p>
    <table class="lines">
        <thead>
            <tr>
                <th style="width:3%">#</th>
                <th style="width:28%">Producto</th>
                <th class="num" style="width:9%">Costo unit.</th>
                <th class="num" style="width:7%">Cant.</th>
                <th class="num" style="width:7%">Desc. %</th>
                <th class="num" style="width:7%">Tasa IVA %</th>
                <th class="num" style="width:9%">IVA (monto)</th>
                <th class="num" style="width:9%">Subtotal línea</th>
                <th class="num" style="width:11%">Total línea</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($purchase->items as $line)
                <tr>
                    <td>{{ $line->line_number ?? $loop->iteration }}</td>
                    <td>
                        {{ $line->product_name_snapshot ?? '—' }}
                        @if(filled($line->sku_snapshot))
                            <br><span style="font-size:6.5pt;color:#555;">{{ $line->sku_snapshot }}</span>
                        @endif
                    </td>
                    <td class="num">{{ $pdfSym }}{{ number_format((float) $line->unit_cost, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $line->quantity_ordered, 3, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $line->line_discount_percent, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $line->line_vat_percent, 2, ',', '.') }}</td>
                    <td class="num">{{ $pdfSym }}{{ number_format((float) $line->tax_amount, 2, ',', '.') }}</td>
                    <td class="num">{{ $pdfSym }}{{ number_format((float) $line->line_subtotal, 2, ',', '.') }}</td>
                    <td class="num">{{ $pdfSym }}{{ number_format((float) $line->line_total, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;color:#666;">Sin líneas registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <p class="summary-title">Totales y desglose del documento</p>
        <table class="breakdown">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Importe</th>
                </tr>
            </thead>
            <tbody>
                @if (filled($purchase->declared_invoice_total))
                    <tr>
                        <td>Total declarado (proveedor)</td>
                        <td>{{ $pdfSym }}{{ number_format((float) $purchase->declared_invoice_total, 2, ',', '.') }}</td>
                    </tr>
                @endif
                <tr>
                    <td>Subtotal (sumatoria de líneas)</td>
                    <td>{{ $pdfSym }}{{ number_format((float) ($purchase->subtotal ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Subtotal sin IVA (exento)</td>
                    <td>{{ $pdfSym }}{{ number_format((float) ($purchase->subtotal_exempt_amount ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Subtotal gravado (base antes desc. documento)</td>
                    <td>{{ $pdfSym }}{{ number_format((float) ($purchase->subtotal_taxable_amount ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Descuentos en líneas (monto)</td>
                    <td>{{ $pdfSym }}{{ number_format((float) ($purchase->discount_total ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Descuento documento (%)</td>
                    <td>{{ number_format((float) ($purchase->document_discount_percent ?? 0), 2, ',', '.') }}%</td>
                </tr>
                <tr>
                    <td>Monto descuento documento</td>
                    <td>{{ $pdfSym }}{{ number_format((float) ($purchase->document_discount_amount ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Base neta sin IVA (tras desc. documento)</td>
                    <td>{{ $pdfSym }}{{ number_format((float) ($purchase->net_exempt_after_document_discount ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Base imponible (tras desc. documento)</td>
                    <td>{{ $pdfSym }}{{ number_format((float) ($purchase->net_taxable_after_document_discount ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>IVA ({{ number_format((float) ($default_vat_rate_percent ?? 0), 2, ',', '.') }}% sobre base imponible)</td>
                    <td>{{ $pdfSym }}{{ number_format((float) ($purchase->tax_total ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total factura</td>
                    <td>{{ $pdfSym }}{{ number_format((float) ($purchase->total ?? 0), 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
        <p class="summary-footnote">
            El descuento documento se aplica por igual sobre el subtotal exento y el gravado. El IVA del documento se calcula con la tasa general vigente
            ({{ number_format((float) ($default_vat_rate_percent ?? 0), 2, ',', '.') }}%) sobre la base imponible resultante. Los montos de IVA por línea reflejan
            solo el detalle de cada ítem (sin el descuento documento global).
        </p>
    </div>

    @if (filled($purchase->notes))
        <p style="margin-top:10px;font-size:8pt;"><strong>Notas:</strong> {{ $purchase->notes }}</p>
    @endif

    <p class="ref">Ref. documento: {{ $pdf_document_ref }} · Documento informativo generado desde {{ config('app.name') }}.</p>
</body>
</html>
