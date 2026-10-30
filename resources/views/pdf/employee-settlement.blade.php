@php
    $money = fn ($amount): string => number_format((float) $amount, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Liquidación {{ $reference }}</title>
    <style>
        @page { margin: 44px 54px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 9.5px; line-height: 1.45; }
        .header { border-bottom: 1px solid #0f766e; padding-bottom: 6px; margin-bottom: 14px; }
        .logo { height: 34px; }
        .reference { float: right; font-size: 9px; color: #6b7280; margin-top: 12px; }
        h1 { text-align: center; font-size: 14px; text-transform: uppercase; letter-spacing: 0.08em; margin: 0 0 12px; }
        h2 { font-size: 10px; text-transform: uppercase; color: #0f766e; margin: 12px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3px 5px; vertical-align: top; text-align: left; }
        .data td { width: 25%; }
        .label { color: #6b7280; }
        .lines th { background: #f3f4f6; }
        .lines td { border-bottom: 1px solid #e5e7eb; }
        .amount { text-align: right; white-space: nowrap; }
        .formula { color: #6b7280; font-size: 8px; }
        .total td { font-weight: 700; border-bottom: 0; }
        .net { background: #ecfdf5; font-size: 11px; font-weight: 700; }
        .draft { text-align: center; color: #b91c1c; font-weight: 700; margin-bottom: 8px; }
        .release { text-align: justify; margin-top: 12px; }
        .signatures { margin-top: 44px; }
        .signatures td { width: 50%; text-align: center; }
        .signature-line { border-top: 1px solid #111827; margin: 0 24px; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        @if (! empty($logo_data_uri))
            <img class="logo" src="{{ $logo_data_uri }}" alt="Farmadoc">
        @endif
        <span class="reference">{{ $reference }}</span>
    </div>

    <h1>Planilla de liquidación y finiquito</h1>

    @unless ($is_final)
        <p class="draft">DOCUMENTO SIN APROBAR · {{ mb_strtoupper($settlement->status->label(), 'UTF-8') }}</p>
    @endunless

    <table class="data">
        <tr>
            <td><span class="label">Trabajador</span><br>{{ $employee->fullName() }}</td>
            <td><span class="label">Cédula</span><br>{{ $employee->formattedNationalId() ?? $employee->national_id }}</td>
            <td><span class="label">Cargo</span><br>{{ $employee->job_title ?: '—' }}</td>
            <td><span class="label">Sucursal</span><br>{{ $employee->branch?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td><span class="label">Fecha de ingreso</span><br>{{ $settlement->hired_at->format('d/m/Y') }}</td>
            <td><span class="label">Último día</span><br>{{ $settlement->last_work_day->format('d/m/Y') }}</td>
            <td><span class="label">Tiempo de servicio</span><br>{{ $settlement->service_label }}</td>
            <td><span class="label">Motivo</span><br>{{ $exit->reason->label() }}</td>
        </tr>
        <tr>
            <td><span class="label">Sueldo mensual</span><br>US$ {{ $money($settlement->monthly_salary_usd) }}</td>
            <td><span class="label">Salario normal diario</span><br>US$ {{ number_format((float) $settlement->normal_daily_usd, 4, ',', '.') }}</td>
            <td><span class="label">Salario integral diario</span><br>US$ {{ number_format((float) $settlement->integral_daily_usd, 4, ',', '.') }}</td>
            <td><span class="label">Tasa BCV</span><br>{{ $settlement->bcv_ves_per_usd ? 'Bs '.number_format((float) $settlement->bcv_ves_per_usd, 4, ',', '.') : '—' }}</td>
        </tr>
    </table>

    @foreach (['Asignaciones' => $assignments, 'Deducciones' => $deductions] as $title => $lines)
        @if ($lines->isNotEmpty())
            <h2>{{ $title }}</h2>
            <table class="lines">
                <tr><th>Concepto</th><th class="amount">Monto (USD)</th></tr>
                @foreach ($lines as $line)
                    <tr>
                        <td>
                            {{ $line->label }}
                            <div class="formula">{{ $line->formula }}@if ($line->isAdjusted()) · Ajustado: {{ $line->adjustment_reason }} (calculado US$ {{ $money($line->calculated_usd) }})@endif</div>
                        </td>
                        <td class="amount">{{ $money($line->amount_usd) }}</td>
                    </tr>
                @endforeach
                <tr class="total"><td>Total {{ mb_strtolower($title, 'UTF-8') }}</td><td class="amount">{{ $money($lines->sum('amount_usd')) }}</td></tr>
            </table>
        @endif
    @endforeach

    <table style="margin-top: 10px;">
        <tr class="net">
            <td>Neto a pagar</td>
            <td class="amount">
                US$ {{ $money($settlement->net_usd) }}
                @if ($settlement->net_ves !== null)
                    · Bs {{ $money($settlement->net_ves) }}
                @endif
            </td>
        </tr>
    </table>

    @if ($settlement->paid_at)
        <p>Pagado el {{ $settlement->paid_at->format('d/m/Y') }} por {{ $settlement->payment_method }}, referencia {{ $settlement->payment_reference }}.</p>
    @endif

    <p class="release">
        El trabajador declara recibir el monto indicado por los conceptos detallados en esta planilla, derivados de la terminación de la relación de trabajo, y deja constancia de haber revisado cada uno de ellos.
    </p>

    <table class="signatures">
        <tr>
            <td><div class="signature-line">Por la empresa</div></td>
            <td><div class="signature-line">El trabajador · C.I. {{ $employee->formattedNationalId() ?? $employee->national_id }}</div></td>
        </tr>
    </table>
</body>
</html>
