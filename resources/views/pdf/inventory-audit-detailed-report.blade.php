<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte detallado de auditorías de inventario</title>
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
            margin-bottom: 4px;
        }
        .filters {
            font-size: 8pt;
            margin: 6px 0 10px;
        }
        .summary,
        .audit-meta,
        .lines {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td,
        .audit-meta td,
        .lines th,
        .lines td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
            vertical-align: top;
        }
        .summary .label,
        .audit-meta .label {
            width: 22%;
            background: #f0fafb;
            font-weight: bold;
        }
        .audit {
            margin-top: 14px;
        }
        .audit h2 {
            font-size: 10pt;
            color: #0e5c5f;
            margin: 0 0 6px 0;
        }
        .lines {
            margin-top: 6px;
            font-size: 7.5pt;
        }
        .lines th {
            background: #f3f4f6;
            font-weight: bold;
            text-align: left;
        }
        .num { text-align: right; }
        .empty {
            margin: 18px 0;
            text-align: center;
            color: #6b7280;
        }
        .ref {
            font-size: 7pt;
            color: #666;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        @if (! empty($pdf_logo_data_uri))
            <img src="{{ $pdf_logo_data_uri }}" alt="Farmadoc">
        @endif
        <h1>Reporte detallado de auditorías de inventario</h1>
        <div class="meta">
            Generado el {{ $generated_at }} por {{ $generated_by }}
        </div>
    </div>

    <p class="filters">
        <strong>Filtros:</strong>
        @if (($filter_labels ?? []) === [])
            Sin filtros adicionales.
        @else
            {{ implode(' · ', $filter_labels) }}
        @endif
    </p>

    <table class="summary">
        <tr>
            <td class="label">Auditorías</td>
            <td>{{ number_format($summary['audits_count'], 0, ',', '.') }}</td>
            <td class="label">Líneas incluidas</td>
            <td>{{ number_format($summary['lines_total'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Pendientes</td>
            <td>{{ number_format($summary['pending'], 0, ',', '.') }}</td>
            <td class="label">Procesadas (sin cambio)</td>
            <td>{{ number_format($summary['verified'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Actualizadas</td>
            <td>{{ number_format($summary['updated'], 0, ',', '.') }}</td>
            <td class="label">Cambios de costo</td>
            <td>{{ number_format($summary['cost_changes'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Suma de delta de existencia</td>
            <td colspan="3">{{ $summary['quantity_delta_sum_label'] }}</td>
        </tr>
    </table>

    @if ($audits === [])
        <p class="empty">No hay auditorías de inventario para los filtros seleccionados.</p>
    @endif

    @foreach ($audits as $audit)
        <div class="audit">
            <h2>Auditoría #{{ $audit['id'] }} · {{ $audit['branch_name'] }} · {{ $audit['status'] }}</h2>
            <table class="audit-meta">
                <tr>
                    <td class="label">Categoría</td>
                    <td>{{ $audit['category_name'] }}</td>
                    <td class="label">Rango alfabético</td>
                    <td>{{ $audit['letter_range'] }}</td>
                </tr>
                <tr>
                    <td class="label">Inicio</td>
                    <td>{{ $audit['started_at'] }} · {{ $audit['started_by'] }}</td>
                    <td class="label">Cierre</td>
                    <td>{{ $audit['closed_at'] }} · {{ $audit['closed_by'] }}</td>
                </tr>
                <tr>
                    <td class="label">Progreso</td>
                    <td>
                        {{ $audit['progress']['total'] }} líneas
                        ({{ $audit['progress']['verified'] }} sin cambios,
                        {{ $audit['progress']['updated'] }} actualizadas,
                        {{ $audit['progress']['pending'] }} pendientes)
                    </td>
                    <td class="label">Notas</td>
                    <td>{{ $audit['notes'] }}</td>
                </tr>
            </table>

            @if ($audit['lines'] === [])
                <p class="empty">Esta auditoría no tiene líneas en el rango solicitado.</p>
            @else
                <table class="lines">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Estado</th>
                            <th class="num">Sist.</th>
                            <th class="num">Contada</th>
                            <th class="num">Delta</th>
                            <th class="num">Costo ant.</th>
                            <th class="num">Costo nuevo</th>
                            <th>Costo Δ</th>
                            <th>Procesó</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($audit['lines'] as $line)
                            <tr>
                                <td>{{ $line['code'] }}</td>
                                <td>{{ $line['name'] }}</td>
                                <td>{{ $line['status'] }}</td>
                                <td class="num">{{ $line['system_quantity'] }}</td>
                                <td class="num">{{ $line['counted_quantity'] }}</td>
                                <td class="num">{{ $line['quantity_delta'] }}</td>
                                <td class="num">{{ $line['system_cost'] }}</td>
                                <td class="num">{{ $line['new_cost'] }}</td>
                                <td>{{ $line['cost_changed'] }}</td>
                                <td>{{ $line['processed_by'] }}</td>
                                <td>{{ $line['processed_at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <p class="ref">Ref. {{ $pdf_document_ref }} · Farmadoc</p>
</body>
</html>
