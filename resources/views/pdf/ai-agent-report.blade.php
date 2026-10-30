<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 36px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 9px; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        p { color: #6b7280; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; text-align: left; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 3px 5px; }
    </style>
</head>
<body>
    <h1>{{ $report->title }}</h1>
    <p>Generado por el Agente IA el {{ $report->created_at->format('d/m/Y h:i a') }} · {{ count($report->rows) }} filas</p>
    <table>
        <thead>
            <tr>
                @foreach ($report->columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($report->rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
