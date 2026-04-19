<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de inventario</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 11px;
            line-height: 1.4;
        }
        .header {
            margin-bottom: 14px;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 10px;
        }
        .logo {
            height: 34px;
            margin-bottom: 8px;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }
        .muted {
            color: #6b7280;
            margin: 0;
        }
        .section-title {
            margin: 14px 0 8px;
            font-size: 13px;
            font-weight: 700;
        }
        .summary-table,
        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            vertical-align: top;
        }
        .summary-label {
            width: 28%;
            background: #f9fafb;
            font-weight: 700;
        }
        .filters-list {
            margin: 4px 0 0;
            padding-left: 16px;
        }
        .detail-table th,
        .detail-table td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
        }
        .detail-table th {
            background: #f3f4f6;
            font-weight: 700;
        }
        .numeric {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(filled($pdf_logo_data_uri ?? null))
            <img class="logo" src="{{ $pdf_logo_data_uri }}" alt="Farmadoc">
        @endif
        <p class="title">Reporte de inventario</p>
        <p class="muted">Generado el {{ $generated_at }} por {{ $generated_by }}</p>
    </div>

    <p class="section-title">Resumen del inventario filtrado</p>
    <table class="summary-table">
        <tr>
            <td class="summary-label">Registros</td>
            <td>{{ $summary['total_items'] }}</td>
            <td class="summary-label">Existencias totales</td>
            <td class="numeric">{{ $summary['total_quantity'] }}</td>
        </tr>
        <tr>
            <td class="summary-label">Disponible total</td>
            <td class="numeric">{{ $summary['total_available'] }}</td>
            <td class="summary-label">Bajo stock</td>
            <td class="numeric">{{ $summary['low_stock_count'] }}</td>
        </tr>
        <tr>
            <td class="summary-label">Costo promedio</td>
            <td class="numeric">{{ $summary['avg_cost_price'] }}</td>
            <td class="summary-label">Precio final promedio (IVA)</td>
            <td class="numeric">{{ $summary['avg_final_price'] }}</td>
        </tr>
        <tr>
            <td class="summary-label">Filtros aplicados</td>
            <td colspan="3">
                @if($filters === [])
                    Sin filtros específicos.
                @else
                    <ul class="filters-list">
                        @foreach($filters as $filter)
                            <li>{{ $filter }}</li>
                        @endforeach
                    </ul>
                @endif
            </td>
        </tr>
    </table>

    <p class="section-title">Detalle de inventario seleccionado</p>
    @if($pdf_is_truncated ?? false)
        <p class="muted">
            El detalle del PDF se limita a las primeras {{ $pdf_detail_limit }} filas por rendimiento.
            Para obtener el total completo usa la salida en formato CSV.
        </p>
    @endif
    <table class="detail-table">
        <thead>
            <tr>
                @foreach($columns as $column)
                    <th>{{ $column_labels[$column] ?? $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $column)
                        @php
                            $isNumeric = in_array($column, ['quantity', 'available_quantity', 'cost_price', 'final_price_without_vat', 'final_price_with_vat'], true);
                        @endphp
                        <td class="{{ $isNumeric ? 'numeric' : '' }}">{{ $row[$column] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}">No hay registros para los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
