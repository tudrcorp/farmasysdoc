<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cierre de caja — {{ $period_full_label }}</title>
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

        /* Marcas de agua (fijas en cada página) */
        .watermark-layer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .watermark-line {
            position: absolute;
            left: 50%;
            width: 140%;
            margin-left: -70%;
            text-align: center;
            font-weight: bold;
            color: #18acb2;
            opacity: 0.09;
            white-space: nowrap;
        }
        .watermark-line.wm-a {
            top: 30%;
            font-size: 38pt;
            transform: rotate(-34deg);
        }
        .watermark-line.wm-b {
            top: 48%;
            font-size: 20pt;
            color: #555555;
            opacity: 0.07;
            transform: rotate(-34deg);
        }
        .watermark-line.wm-c {
            top: 64%;
            font-size: 13pt;
            color: #888888;
            opacity: 0.08;
            transform: rotate(-34deg);
        }

        .content-shell {
            position: relative;
            z-index: 1;
            padding: 4px 2px 16px 2px;
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
            font-size: 16pt;
            margin: 0 0 6px 0;
            padding: 0;
            color: #0e5c5f;
            font-weight: bold;
            border: none;
            text-align: center;
        }
        .doc-sub {
            font-size: 8.5pt;
            color: #444;
            line-height: 1.45;
            text-align: center;
        }
        .doc-badge {
            display: inline-block;
            margin-top: 8px;
            background: #fce422;
            color: #1a1a1a;
            font-size: 7pt;
            font-weight: bold;
            padding: 3px 10px;
            letter-spacing: 0.04em;
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
        }
        .doc-header .meta {
            margin: 12px auto 0 auto;
            max-width: 100%;
            text-align: center;
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
        .summary-grid {
            background: #ffffff;
            border: 1px solid #c5e8ea;
        }
        .summary-grid td { border: none; padding: 3px 8px 3px 0; }
        .summary-grid td:first-child { font-weight: bold; width: 42%; color: #0e5c5f; }
        .summary-grid tr:nth-child(even) td { background: transparent; }

        .sale-block {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px dashed #9ccfd2;
            page-break-inside: avoid;
        }
        .sale-header {
            background: #e8f6f7;
            color: #0a4d50;
            border-left: 4px solid #18acb2;
            padding: 7px 10px;
            margin-bottom: 6px;
            font-size: 9pt;
            font-weight: bold;
        }
        .items th { font-size: 7.5pt; }
        .items td { font-size: 7.5pt; }
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
        .security-strip {
            margin-top: 8px;
            font-size: 7pt;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="watermark-layer" aria-hidden="true">
        <div class="watermark-line wm-a">{{ $app_name }}</div>
        <div class="watermark-line wm-b">USO INTERNO · NO ES COMPROBANTE FISCAL</div>
        <div class="watermark-line wm-c">Ref. {{ $pdf_document_ref }} · {{ $period_full_label }}</div>
    </div>

    <div class="content-shell">
        <header class="doc-header">
            @if (filled($pdf_logo_data_uri ?? null))
                <div class="doc-header-logo">
                    <img src="{{ $pdf_logo_data_uri }}" alt="{{ $app_name }}">
                </div>
            @endif
            <h1 class="doc-title">Cierre de caja — reporte de ventas</h1>
            <div class="doc-sub">
                <strong>{{ $app_name }}</strong><br>
                Período: <strong>{{ $period_full_label }}</strong>
            </div>
            <div>
                <span class="doc-badge">DOCUMENTO INTERNO</span>
            </div>
            <div class="meta">
                Generado: <strong>{{ $generated_at }}</strong><br>
                Usuario: <strong>{{ $generated_by }}</strong><br>
                Alcance: <strong>{{ $scope_note }}</strong><br>
                Huella documento: <strong>{{ $pdf_document_ref }}</strong>
            </div>
        </header>

        <h2>Resumen consolidado</h2>
        <table class="summary-grid">
            <tr><td>Número de ventas</td><td class="num">{{ $summary['sale_count'] }}</td></tr>
            <tr><td>Líneas de detalle (ítems)</td><td class="num">{{ $summary['items_count'] }}</td></tr>
            <tr><td>Unidades vendidas (suma cantidades)</td><td class="num">{{ number_format($summary['quantity_sold'], 3, ',', '.') }}</td></tr>
            <tr><td>Subtotal bruto (documentos)</td><td class="num">$ {{ number_format($summary['subtotal'], 2, ',', '.') }}</td></tr>
            <tr><td>Impuestos totales</td><td class="num">$ {{ number_format($summary['tax_total'], 2, ',', '.') }}</td></tr>
            <tr><td>Descuentos totales (documento)</td><td class="num">$ {{ number_format($summary['discount_total'], 2, ',', '.') }}</td></tr>
            <tr><td>Total ventas (USD documento)</td><td class="num"><strong>$ {{ number_format($summary['grand_total'], 2, ',', '.') }}</strong></td></tr>
            <tr><td>Suma cobros registrados en USD</td><td class="num">$ {{ number_format($summary['payment_usd_sum'], 2, ',', '.') }}</td></tr>
            <tr><td>Suma cobros registrados en VES</td><td class="num">Bs. {{ number_format($summary['payment_ves_sum'], 2, ',', '.') }}</td></tr>
            <tr><td>Utilidad bruta acumulada (ítems)</td><td class="num">$ {{ number_format($summary['gross_profit_sum'], 2, ',', '.') }}</td></tr>
        </table>

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
                    <th class="num">Total documento (USD)</th>
                    <th class="num">Cobro USD</th>
                    <th class="num">Cobro VES (Bs.)</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($payment_breakdown as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="center">{{ $row['count'] }}</td>
                        <td class="num">$ {{ number_format($row['total_document'], 2, ',', '.') }}</td>
                        <td class="num">$ {{ number_format($row['payment_usd'], 2, ',', '.') }}</td>
                        <td class="num">Bs. {{ number_format($row['payment_ves'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <td>Total (todos los métodos)</td>
                    <td class="center">{{ $pt['count'] }}</td>
                    <td class="num">$ {{ number_format($pt['total_document'], 2, ',', '.') }}</td>
                    <td class="num">$ {{ number_format($pt['payment_usd'], 2, ',', '.') }}</td>
                    <td class="num">Bs. {{ number_format($pt['payment_ves'], 2, ',', '.') }}</td>
                </tr>
                </tfoot>
            </table>
        @endif

        <h2>Detalle por venta</h2>
        @forelse ($sales as $sale)
            @php
                $effectiveAt = $sale->sold_at ?? $sale->created_at;
                $clientLabel = $sale->client !== null
                    ? ($sale->client->name ?? ('Cliente #'.$sale->client_id))
                    : 'Mostrador / sin cliente';
            @endphp
            <div class="sale-block">
                <div class="sale-header">
                    {{ $sale->sale_number }}
                    · {{ $effectiveAt !== null ? $effectiveAt->format('d/m/Y H:i') : '—' }}
                    · Sucursal: {{ $sale->branch?->name ?? '—' }}
                    · Cliente: {{ $clientLabel }}
                </div>
                <table>
                    <tbody>
                    <tr>
                        <td style="width:22%"><strong>Estado</strong></td>
                        <td>{{ $sale->status?->label() ?? '—' }}</td>
                        <td style="width:22%"><strong>Forma de pago</strong></td>
                        <td>
                            @php
                                $pm = strtolower((string) ($sale->payment_method ?? ''));
                                $pmLabel = match ($pm) {
                                    'efectivo_usd' => 'Efectivo USD',
                                    'efectivo_ves' => 'Efectivo VES',
                                    'transfer_ves' => 'Transferencia VES',
                                    'zelle' => 'Zelle',
                                    'pago_movil' => 'Pago Movil',
                                    'mixed' => 'Pago Multiple',
                                    'transfer_usd' => 'Transferencias USD',
                                    default => filled($sale->payment_method) ? $sale->payment_method : '—',
                                };
                            @endphp
                            {{ $pmLabel }}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Referencia</strong></td>
                        <td>{{ filled($sale->reference) ? $sale->reference : '—' }}</td>
                        <td><strong>BCV (Bs/USD)</strong></td>
                        <td>{{ $sale->bcv_ves_per_usd !== null ? number_format((float) $sale->bcv_ves_per_usd, 6, ',', '.') : '—' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Subtotal</strong></td>
                        <td class="num">$ {{ number_format((float) $sale->subtotal, 2, ',', '.') }}</td>
                        <td><strong>Impuesto</strong></td>
                        <td class="num">$ {{ number_format((float) $sale->tax_total, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Descuento</strong></td>
                        <td class="num">$ {{ number_format((float) $sale->discount_total, 2, ',', '.') }}</td>
                        <td><strong>Total</strong></td>
                        <td class="num"><strong>$ {{ number_format((float) $sale->total, 2, ',', '.') }}</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Pago USD</strong></td>
                        <td class="num">$ {{ number_format((float) $sale->payment_usd, 2, ',', '.') }}</td>
                        <td><strong>Pago VES</strong></td>
                        <td class="num">Bs. {{ number_format((float) $sale->payment_ves, 2, ',', '.') }}</td>
                    </tr>
                    @if (filled($sale->notes))
                        <tr>
                            <td><strong>Notas</strong></td>
                            <td colspan="3">{{ $sale->notes }}</td>
                        </tr>
                    @endif
                    </tbody>
                </table>

                <table class="items">
                    <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="center">SKU</th>
                        <th class="num">Cant.</th>
                        <th class="num">P. unit.</th>
                        <th class="num">Desc.</th>
                        <th class="num">Subtotal</th>
                        <th class="num">Impuesto</th>
                        <th class="num">Total línea</th>
                        <th class="num">Costo u.</th>
                        <th class="num">Util. bruta</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($sale->items as $item)
                        <tr>
                            <td>{{ $item->product_name_snapshot ?? '—' }}</td>
                            <td class="center">{{ $item->sku_snapshot ?? '—' }}</td>
                            <td class="num">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) $item->discount_amount, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) $item->line_subtotal, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) $item->tax_amount, 2, ',', '.') }}</td>
                            <td class="num"><strong>{{ number_format((float) $item->line_total, 2, ',', '.') }}</strong></td>
                            <td class="num">{{ number_format((float) $item->unit_cost, 4, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) ($item->gross_profit ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="muted">No hay ventas completadas en el período seleccionado.</p>
        @endforelse

        <div class="footer-note">
            Documento generado automáticamente para control interno. Conserve este archivo según la política de archivo de su farmacia.
            <div class="security-strip">
                No altere ni reutilice este PDF como comprobante fiscal. La huella <strong>{{ $pdf_document_ref }}</strong> identifica esta emisión.
            </div>
        </div>
    </div>
</body>
</html>
