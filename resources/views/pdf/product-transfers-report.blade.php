<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Traslados — {{ $from->format('d/m/Y') }} – {{ $until->format('d/m/Y') }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 12mm 10mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
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
            font-size: 14pt;
            color: #0e5c5f;
            margin: 8px 0 4px 0;
        }
        .meta {
            font-size: 7.5pt;
            color: #444;
            margin-bottom: 8px;
        }
        table.main {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.main th, table.main td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            vertical-align: top;
        }
        table.main th {
            background: #f0fafb;
            font-weight: bold;
            text-align: left;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            margin-top: 4px;
        }
        table.items th, table.items td {
            border: 1px solid #ddd;
            padding: 3px 5px;
        }
        table.items th { background: #f7f7f7; }
        .num { text-align: right; }
        .summary {
            margin-top: 14px;
            padding: 8px;
            background: #f0fafb;
            font-size: 9pt;
            font-weight: bold;
        }
        .ref { font-size: 7pt; color: #666; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        @if (!empty($pdf_logo_data_uri))
            <img src="{{ $pdf_logo_data_uri }}" alt="Logo" />
        @endif
        <h1>Reporte de traslados entre sucursales</h1>
        <div class="meta">
            Período: <strong>{{ $from->format('d/m/Y') }}</strong> — <strong>{{ $until->format('d/m/Y H:i') }}</strong>
            · Generado: {{ $generated_at }} · Usuario: {{ $generated_by }}
        </div>
    </div>

    @forelse ($transfers as $transfer)
        <table class="main">
            <tr>
                <th style="width:18%">Código</th>
                <td>{{ $transfer->code }}</td>
                <th style="width:12%">Estado</th>
                <td>{{ \App\Enums\ProductTransferStatus::labelForStored($transfer->status) }}</td>
            </tr>
            <tr>
                <th>Origen</th>
                <td>{{ $transfer->fromBranch?->name ?? '—' }}</td>
                <th>Destino</th>
                <td>{{ $transfer->toBranch?->name ?? '—' }}</td>
            </tr>
            <tr>
                <th>Creado por</th>
                <td>{{ $transfer->created_by }}</td>
                <th>Registro</th>
                <td>{{ $transfer->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            @if (\App\Enums\ProductTransferStatus::isCompletedValue($transfer->status))
                <tr>
                    <th>Completado por</th>
                    <td>{{ $transfer->completed_by ?? '—' }}</td>
                    <th>Completado el</th>
                    <td>{{ $transfer->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Costo total</th>
                    <td colspan="3" class="num">${{ number_format((float) ($transfer->total_transfer_cost ?? 0), 2, '.', ',') }} USD</td>
                </tr>
            @endif
            <tr>
                <td colspan="4">
                    <strong>Productos</strong>
                    <table class="items">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="num">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transfer->items as $item)
                                <tr>
                                    <td>{{ $item->product?->name ?? '—' }}</td>
                                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
        <div style="height:8px"></div>
    @empty
        <p>No hay traslados en el período seleccionado.</p>
    @endforelse

    <div class="summary">
        Total documentos: {{ $transfers->count() }} · Líneas de producto: {{ $total_lines }}
        · Suma costos completados: ${{ number_format($total_cost_sum, 2, '.', ',') }} USD
    </div>
    <p class="ref">Ref. documento: {{ $pdf_document_ref ?? '—' }}</p>
</body>
</html>
