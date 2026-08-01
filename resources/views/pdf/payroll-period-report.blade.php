<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nómina — {{ $period->label() }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 9px;
            line-height: 1.35;
        }
        .header {
            margin-bottom: 12px;
            border-bottom: 2px solid #0d9488;
            padding-bottom: 8px;
        }
        .logo {
            height: 72px;
            margin-bottom: 10px;
        }
        .title {
            font-size: 16px;
            font-weight: 700;
            margin: 0;
        }
        .muted {
            color: #6b7280;
            margin: 2px 0 0;
        }
        .section-title {
            margin: 12px 0 6px;
            font-size: 11px;
            font-weight: 700;
        }
        .summary-table,
        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            border: 1px solid #e5e7eb;
            padding: 5px 7px;
            vertical-align: top;
        }
        .summary-label {
            width: 22%;
            background: #f0fdfa;
            font-weight: 700;
        }
        .detail-table th,
        .detail-table td {
            border: 1px solid #d1d5db;
            padding: 4px 5px;
            text-align: left;
            vertical-align: top;
        }
        .detail-table th {
            background: #f3f4f6;
            font-weight: 700;
            font-size: 8px;
        }
        .numeric {
            text-align: right;
            white-space: nowrap;
        }
        .pay-usd {
            color: #047857;
            font-weight: 700;
        }
        .pay-ves {
            color: #1d4ed8;
            font-weight: 700;
        }
        .totals-row td {
            background: #f9fafb;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(filled($pdf_logo_data_uri ?? null))
            <img class="logo" src="{{ $pdf_logo_data_uri }}" alt="Farmadoc">
        @endif
        <p class="title">Detalle de nómina</p>
        <p class="muted">
            {{ $period->label() }}
            · {{ $period->halfLabel() }}
            · {{ $period->monthLabel() }}
            · {{ $period->status?->label() ?? '—' }}
        </p>
        <p class="muted">Generado el {{ $generated_at }} por {{ $generated_by }}</p>
    </div>

    <p class="section-title">Resumen del periodo</p>
    <table class="summary-table">
        <tr>
            <td class="summary-label">Empleados</td>
            <td>{{ $totals['employees'] }}</td>
            <td class="summary-label">Tasa BCV</td>
            <td class="numeric">
                {{ $period->bcv_ves_per_usd !== null
                    ? number_format((float) $period->bcv_ves_per_usd, 6, ',', '.')
                    : '—' }}
            </td>
        </tr>
        <tr>
            <td class="summary-label">Base USD</td>
            <td class="numeric">{{ number_format($totals['base_salary_usd'], 2, ',', '.') }}</td>
            <td class="summary-label">Asignaciones USD</td>
            <td class="numeric">{{ number_format($totals['assignments_usd'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="summary-label">Deducciones USD</td>
            <td class="numeric">{{ number_format($totals['deductions_usd'], 2, ',', '.') }}</td>
            <td class="summary-label">Préstamos USD</td>
            <td class="numeric">{{ number_format($totals['loans_usd'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="summary-label">Pagar USD</td>
            <td class="numeric pay-usd">{{ number_format($totals['cash_paid_usd'], 2, ',', '.') }}</td>
            <td class="summary-label">Pagar Bs</td>
            <td class="numeric pay-ves">{{ number_format($totals['cash_paid_ves'], 2, ',', '.') }}</td>
        </tr>
    </table>

    <p class="section-title">Detalle por empleado</p>
    <table class="detail-table">
        <thead>
            <tr>
                <th>Empleado</th>
                <th>Cédula</th>
                <th>Sucursal</th>
                <th class="numeric">Base</th>
                <th class="numeric">Asig.</th>
                <th class="numeric">Ded.</th>
                <th class="numeric">Prést.</th>
                <th class="numeric">Pagar USD</th>
                <th class="numeric">Pagar Bs</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line->employee?->fullName() ?? '—' }}</td>
                    <td>{{ $line->employee?->national_id ?? '—' }}</td>
                    <td>{{ $line->employee?->branch?->name ?? '—' }}</td>
                    <td class="numeric">{{ number_format((float) $line->base_salary_usd, 2, ',', '.') }}</td>
                    <td class="numeric">{{ number_format((float) $line->assignments_usd, 2, ',', '.') }}</td>
                    <td class="numeric">{{ number_format((float) $line->deductions_usd, 2, ',', '.') }}</td>
                    <td class="numeric">{{ number_format((float) $line->loans_usd, 2, ',', '.') }}</td>
                    <td class="numeric pay-usd">{{ number_format((float) $line->cash_paid_usd, 2, ',', '.') }}</td>
                    <td class="numeric pay-ves">{{ number_format((float) $line->cash_paid_ves, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">Sin líneas de nómina.</td>
                </tr>
            @endforelse
            @if ($lines->isNotEmpty())
                <tr class="totals-row">
                    <td colspan="3">Totales</td>
                    <td class="numeric">{{ number_format($totals['base_salary_usd'], 2, ',', '.') }}</td>
                    <td class="numeric">{{ number_format($totals['assignments_usd'], 2, ',', '.') }}</td>
                    <td class="numeric">{{ number_format($totals['deductions_usd'], 2, ',', '.') }}</td>
                    <td class="numeric">{{ number_format($totals['loans_usd'], 2, ',', '.') }}</td>
                    <td class="numeric pay-usd">{{ number_format($totals['cash_paid_usd'], 2, ',', '.') }}</td>
                    <td class="numeric pay-ves">{{ number_format($totals['cash_paid_ves'], 2, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
