<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo de nómina — {{ $receipt->worker_name }}</title>
    <style>
        @page { margin: 22px 26px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 8px; line-height: 1.25; }
        .header { border-bottom: 1px solid #0f766e; padding-bottom: 4px; margin-bottom: 6px; }
        .logo { height: 28px; }
        .title { font-size: 11px; font-weight: 700; margin: 3px 0 0; text-transform: uppercase; letter-spacing: 0.04em; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .meta td { padding: 1px 0; vertical-align: top; }
        .meta .label { width: 34%; font-weight: 700; }
        .cols { width: 100%; border-collapse: collapse; }
        .cols > tbody > tr > td { width: 50%; vertical-align: top; }
        .cols > tbody > tr > td:first-child { padding-right: 4px; }
        .cols > tbody > tr > td:last-child { padding-left: 4px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th, table.items td { border: 1px solid #d1d5db; padding: 2px 4px; }
        table.items th { background: #f0fdfa; text-align: left; font-size: 7.5px; }
        .num { text-align: right; white-space: nowrap; }
        .plus { color: #047857; }
        .minus { color: #b45309; }
        .sub td { font-weight: 700; background: #f8fafc; }
        .total { margin-top: 6px; border: 1px solid #0f766e; background: #ecfdf5; padding: 4px 6px; font-weight: 700; }
        .total .num { float: right; }
        .signs-wrap { text-align: center; margin-top: 10px; }
        .signs { width: auto; margin: 0 auto; border-collapse: collapse; }
        .signs td { width: auto; text-align: center; vertical-align: bottom; padding: 0; }
        .signs td:first-child { padding-right: 5px; }
        .sign-img { height: 40px; max-width: 90px; display: block; }
        .sign-caption { margin-top: 2px; font-size: 6.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .footer { margin-top: 8px; text-align: center; }
        .footer .name { font-size: 10px; font-weight: 700; text-transform: uppercase; margin: 0; }
        .footer .id { margin: 1px 0 4px; }
        .footer .branch { font-weight: 700; margin: 0; text-transform: uppercase; }
        .footer .addr { margin: 1px 0 0; color: #4b5563; font-size: 7.5px; }
    </style>
</head>
<body>
    @php
        $assignments = $receipt->assignmentItems();
        $deductions = $receipt->deductionItems();
        $rows = max(count($assignments), count($deductions), 1);
    @endphp

    <div class="header">
        @if (! empty($pdf_logo_data_uri))
            <img class="logo" src="{{ $pdf_logo_data_uri }}" alt="Farmadoc">
        @endif
        <p class="title">Recibo de nómina</p>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Nombre del Trabajador:</td>
            <td>{{ $receipt->worker_name }}</td>
        </tr>
        <tr>
            <td class="label">C.I.:</td>
            <td>{{ $receipt->national_id ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Sueldo Mensual:</td>
            <td>Bs {{ number_format((float) $receipt->legal_salary_monthly_ves, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Mes correspondiente:</td>
            <td>{{ $receipt->month_label }}</td>
        </tr>
    </table>

    <table class="cols">
        <tr>
            <td>
                <table class="items">
                    <thead>
                        <tr>
                            <th>Asignaciones</th>
                            <th class="num">Bs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 0; $i < $rows; $i++)
                            @php $item = $assignments[$i] ?? null; @endphp
                            <tr>
                                <td>{{ $item['name'] ?? '' }}</td>
                                <td class="num plus">
                                    @if ($item)
                                        + {{ number_format((float) ($item['amount_ves'] ?? 0), 2, ',', '.') }}
                                    @endif
                                </td>
                            </tr>
                        @endfor
                        <tr class="sub">
                            <td>Total asignaciones</td>
                            <td class="num plus">+ {{ number_format((float) $receipt->assignments_ves, 2, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
            <td>
                <table class="items">
                    <thead>
                        <tr>
                            <th>Deducciones</th>
                            <th class="num">Bs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 0; $i < $rows; $i++)
                            @php $item = $deductions[$i] ?? null; @endphp
                            <tr>
                                <td>{{ $item['name'] ?? '' }}</td>
                                <td class="num minus">
                                    @if ($item)
                                        − {{ number_format((float) ($item['amount_ves'] ?? 0), 2, ',', '.') }}
                                    @endif
                                </td>
                            </tr>
                        @endfor
                        <tr class="sub">
                            <td>Total deducciones</td>
                            <td class="num minus">− {{ number_format((float) $receipt->deductions_ves, 2, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="total">
        Total a pagar
        <span class="num">Bs {{ number_format((float) $receipt->total_ves, 2, ',', '.') }}</span>
    </div>

    <div class="signs-wrap">
    <table class="signs">
        <tr>
            <td>
                @if (! empty($signature_data_uri))
                    <img class="sign-img" src="{{ $signature_data_uri }}" alt="Firma">
                @else
                    <div style="height: 48px;"></div>
                @endif
                <div class="sign-caption">Firma digital</div>
            </td>
            <td>
                @if (! empty($fingerprint_data_uri))
                    <img class="sign-img" src="{{ $fingerprint_data_uri }}" alt="Huella">
                @else
                    <div style="height: 48px;"></div>
                @endif
                <div class="sign-caption">Huella dactilar</div>
            </td>
        </tr>
    </table>
    </div>

    <div class="footer">
        <p class="name">{{ $receipt->worker_name }}</p>
        <p class="id">{{ $receipt->national_id ?: '—' }}</p>
        <p class="branch">{{ $receipt->branch_name ?: 'Sin sucursal' }}</p>
        <p class="addr">{{ $receipt->branch_address ?: '—' }}</p>
    </div>
</body>
</html>
