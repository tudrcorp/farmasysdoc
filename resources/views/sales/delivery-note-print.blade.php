<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nota de entrega — {{ $sale->sale_number }}</title>
    <style>
        body {
            margin: 0;
            padding: 1rem;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            font-size: 12px;
            color: #111827;
            background: #f3f4f6;
        }

        .toolbar {
            position: relative;
            z-index: 60;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
        }

        .toolbar a,
        .toolbar button {
            display: inline-flex;
            align-items: center;
            border-radius: 0.375rem;
            padding: 0.4rem 0.75rem;
            font-size: 0.8125rem;
            text-decoration: none;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
        }

        .toolbar .primary {
            background: #111827;
            color: #fff;
            border-color: #111827;
        }

        .sheet {
            position: relative;
            z-index: 1;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .doc-top {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem 1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #18acb2;
        }

        .doc-top-logo img {
            display: block;
            max-height: 48px;
            width: auto;
        }

        .doc-top-title-wrap {
            flex: 1;
            min-width: 200px;
        }

        .doc-top-title-wrap h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #0e5c5f;
        }

        .doc-top-title-wrap .doc-subtitle {
            margin: 0.25rem 0 0 0;
            font-size: 0.75rem;
            color: #6b7280;
        }

        /* Marca de agua: 3 logos, misma inclinación, repartidos en vertical (cada hoja al imprimir). */
        .watermark-layer {
            position: fixed;
            inset: 0;
            z-index: 50;
            pointer-events: none;
            overflow: hidden;
        }

        .watermark-mark {
            position: absolute;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            transform-origin: center center;
        }

        .watermark-mark img {
            display: block;
            width: min(520px, 82vw);
            max-width: none;
            height: auto;
            opacity: 0.06;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .watermark-mark.wm-1 {
            top: 22%;
        }

        .watermark-mark.wm-2 {
            top: 50%;
        }

        .watermark-mark.wm-3 {
            top: 78%;
        }

        .header {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .block {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.75rem;
            background: #fafafa;
        }

        .title {
            margin: 0 0 0.5rem 0;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .meta {
            margin: 0;
            line-height: 1.4;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.75rem;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 0.5rem 0.4rem;
            text-align: left;
            vertical-align: top;
        }

        th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #4b5563;
        }

        .right {
            text-align: right;
        }

        .totals {
            margin-top: 1rem;
            display: grid;
            gap: 0.35rem;
            justify-content: end;
            font-size: 0.875rem;
        }

        .totals strong {
            font-size: 1rem;
        }

        @media print {
            body {
                padding: 0;
                background: #fff;
            }

            .toolbar {
                display: none !important;
            }

            .sheet {
                border: none;
                border-radius: 0;
                padding: 0;
            }

            @page {
                margin: 8mm;
            }
        }
    </style>
</head>
<body>
    @if(filled($pdf_logo_data_uri ?? null))
        <div class="watermark-layer" aria-hidden="true">
            <span class="watermark-mark wm-1"><img src="{{ $pdf_logo_data_uri }}" alt=""></span>
            <span class="watermark-mark wm-2"><img src="{{ $pdf_logo_data_uri }}" alt=""></span>
            <span class="watermark-mark wm-3"><img src="{{ $pdf_logo_data_uri }}" alt=""></span>
        </div>
    @endif

    <div class="toolbar">
        <a href="{{ $saleViewUrl }}" class="primary">Ver venta</a>
        <a href="{{ $salesIndexUrl }}">Listado de ventas</a>
        <button type="button" onclick="window.print()">Imprimir de nuevo</button>
    </div>

    <main class="sheet">
        <header class="doc-top">
            @if(filled($pdf_logo_data_uri ?? null))
                <div class="doc-top-logo">
                    <img src="{{ $pdf_logo_data_uri }}" alt="{{ $app_name ?? config('app.name') }}">
                </div>
            @endif
            <div class="doc-top-title-wrap">
                <h1>Nota de entrega</h1>
                <p class="doc-subtitle">{{ $app_name ?? config('app.name') }} · Documento de entrega de mercancía</p>
            </div>
        </header>

        <section class="header">
            <div class="block">
                <h2 class="title">Datos de la venta</h2>
                <p class="meta">Nro. venta: <strong>{{ $sale->sale_number }}</strong></p>
                <p class="meta">Fecha: {{ optional($sale->sold_at)->format('d/m/Y H:i') ?? '—' }}</p>
                <p class="meta">Sucursal: {{ $sale->branch?->name ?? '—' }}</p>
                <p class="meta">Método de cobro: {{ $sale->payment_method ?? '—' }}</p>
                <p class="meta">Estatus de pago: {{ $sale->payment_status ?? '—' }}</p>
            </div>
            <div class="block">
                <h2 class="title">Datos del cliente</h2>
                <p class="meta">Nombre: {{ $sale->client?->name ?? 'Mostrador / sin cliente' }}</p>
                <p class="meta">Documento: {{ $sale->client?->document_number ?? '—' }}</p>
                <p class="meta">Teléfono: {{ $sale->client?->phone ?? '—' }}</p>
            </div>
        </section>

        <section>
            <h2 class="title">Items de la venta</h2>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="right">Cant.</th>
                        <th class="right">Precio unitario</th>
                        <th class="right">Impuesto</th>
                        <th class="right">Total línea</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sale->items as $item)
                        <tr>
                            <td>{{ $item->product_name_snapshot ?: ($item->product?->name ?? 'Producto') }}</td>
                            <td class="right">{{ number_format((float) $item->quantity, 2, '.', ',') }}</td>
                            <td class="right">${{ number_format((float) $item->unit_price, 2, '.', ',') }}</td>
                            <td class="right">${{ number_format((float) $item->tax_amount, 2, '.', ',') }}</td>
                            <td class="right">${{ number_format((float) $item->line_total, 2, '.', ',') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="totals">
            <div>Subtotal: ${{ number_format((float) $sale->subtotal, 2, '.', ',') }}</div>
            <div>Impuestos: ${{ number_format((float) $sale->tax_total, 2, '.', ',') }}</div>
            <div>IGTF: ${{ number_format((float) $sale->igtf_total, 2, '.', ',') }}</div>
            <div>Descuento: ${{ number_format((float) $sale->discount_total, 2, '.', ',') }}</div>
            <div><strong>Total: ${{ number_format((float) $sale->total, 2, '.', ',') }}</strong></div>
        </section>
    </main>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 200);
        });
    </script>
</body>
</html>
