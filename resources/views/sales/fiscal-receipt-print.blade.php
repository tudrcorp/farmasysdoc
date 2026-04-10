<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura fiscal — {{ $sale->sale_number }}</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            margin: 0;
            padding: 1rem;
            font-family: ui-monospace, 'Cascadia Code', 'SF Mono', Menlo, Consolas, monospace;
            font-size: 11px;
            line-height: 1.35;
            color: #111;
            background: #f4f4f5;
        }

        .farmadoc-print-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            background: #fff;
            border-radius: 0.5rem;
            border: 1px solid #e4e4e7;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
        }

        .farmadoc-print-toolbar a {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.8125rem;
            font-weight: 500;
            text-decoration: none;
        }

        .farmadoc-print-toolbar a.primary {
            background: #18181b;
            color: #fafafa;
        }

        .farmadoc-print-toolbar a.secondary {
            background: #f4f4f5;
            color: #18181b;
            border: 1px solid #d4d4d8;
        }

        .farmadoc-print-hint {
            flex: 1 1 100%;
            font-size: 0.75rem;
            color: #52525b;
        }

        pre {
            margin: 0;
            padding: 1rem;
            white-space: pre-wrap;
            word-break: break-word;
            background: #fff;
            border: 1px solid #e4e4e7;
            border-radius: 0.5rem;
        }

        @media print {
            body {
                padding: 0;
                background: #fff;
            }

            .farmadoc-print-toolbar {
                display: none !important;
            }

            pre {
                padding: 0;
                border: none;
                border-radius: 0;
            }

            @page {
                margin: 4mm;
            }
        }
    </style>
</head>
<body>
    <div class="farmadoc-print-toolbar">
        <span class="farmadoc-print-hint">
            Elija la impresora fiscal o térmica y confirme.
            <strong>Importante:</strong> en el cuadro de impresión desactive «Encabezados y pies de página» (Chrome/Edge) o equivalente;
            si no, el navegador añadirá arriba el título y la URL y eso sí saldría en el papel aunque no forme parte del ticket del sistema.
        </span>
        <a href="{{ $saleViewUrl }}" class="primary">Ver venta</a>
        <a href="{{ $salesIndexUrl }}" class="secondary">Listado de ventas</a>
        <button type="button" class="secondary" style="cursor: pointer; font: inherit;" onclick="window.print()">
            Imprimir de nuevo
        </button>
    </div>

    <pre>{{ $plain }}</pre>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 200);
        });
    </script>
</body>
</html>
