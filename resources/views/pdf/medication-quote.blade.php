<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $quote->number }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1c2833; font-size: 10pt; margin: 0; }
        .band { background: #0E949A; color: #fff; padding: 16px 18px; }
        .brand { font-size: 15pt; font-weight: 700; letter-spacing: 0.2px; }
        .rif { font-size: 8.5pt; margin-top: 3px; }
        .address { font-size: 8pt; margin-top: 4px; line-height: 1.35; }
        .doc { float: right; text-align: right; }
        .doc .k { font-size: 8pt; letter-spacing: 1px; text-transform: uppercase; }
        .doc .n { font-size: 13pt; font-weight: 700; margin-top: 2px; }
        .clear { clear: both; }
        h1 { font-size: 16pt; margin: 18px 0 4px; color: #0E949A; }
        .lead { margin: 0 0 14px; color: #52616b; font-size: 9.5pt; }
        .card { border: 1px solid #d5e3e4; background: #f4fbfb; padding: 10px 12px; margin-bottom: 14px; }
        .card .label { font-size: 7.5pt; letter-spacing: 0.6px; text-transform: uppercase; color: #0E949A; font-weight: 700; }
        .card .name { font-size: 12pt; font-weight: 700; margin: 2px 0 6px; }
        .meta { width: 100%; }
        .meta td { font-size: 9pt; padding: 1px 8px 1px 0; vertical-align: top; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #12343b; color: #fff; font-size: 8pt; text-align: left; padding: 7px 8px; }
        table.items td { border-bottom: 1px solid #e4ecec; padding: 7px 8px; font-size: 9.5pt; }
        table.items tr:nth-child(even) td { background: #f7fbfb; }
        .num { text-align: right; white-space: nowrap; }
        .total-wrap { margin-top: 12px; width: 100%; }
        .total-box { float: right; width: 240px; background: #0E949A; color: #fff; padding: 12px 14px; }
        .total-box .lbl { font-size: 8pt; letter-spacing: 0.8px; text-transform: uppercase; }
        .total-box .amt { font-size: 16pt; font-weight: 700; margin-top: 2px; }
        .notes { margin-top: 18px; font-size: 9pt; color: #334047; }
        .foot { margin-top: 22px; font-size: 8pt; color: #607078; line-height: 1.45; }
    </style>
</head>
<body>
    @php
        $money = static fn (float $amount): string => 'USD '.number_format($amount, 2, ',', '.');
        $qty = static fn (float $amount): string => rtrim(rtrim(number_format($amount, 3, ',', '.'), '0'), ',');
    @endphp

    <div class="band">
        <div class="doc">
            <div class="k">Cotización</div>
            <div class="n">{{ $quote->number }}</div>
            <div>{{ $quote->created_at?->timezone(config('app.timezone'))->format('d/m/Y') }}</div>
        </div>
        <div class="brand">{{ $company_name }}</div>
        <div class="rif">RIF {{ $company_rif }}</div>
        <div class="address">{{ $company_address }}</div>
        <div class="clear"></div>
    </div>

    <h1>Cotización de medicamentos</h1>
    <p class="lead">Detalle preparado para el solicitante. Los importes están expresados en dólares.</p>

    <div class="card">
        <div class="label">Solicitante</div>
        <div class="name">{{ $quote->requester_name }}</div>
        <table class="meta">
            <tr>
                <td><strong>Cédula o RIF:</strong> {{ $quote->requester_document }}</td>
                <td><strong>Teléfono:</strong> {{ $quote->requester_phone }}</td>
                <td><strong>Correo:</strong> {{ $quote->requester_email }}</td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Medicamento</th>
                <th class="num" style="width: 14%;">Cantidad</th>
                <th class="num" style="width: 20%;">Precio unitario</th>
                <th class="num" style="width: 20%;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['description'] }}</td>
                    <td class="num">{{ $qty((float) $line['quantity']) }}</td>
                    <td class="num">{{ $money((float) $line['unit_price']) }}</td>
                    <td class="num">{{ $money((float) $line['line_total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-wrap">
        <div class="total-box">
            <div class="lbl">Total de la cotización</div>
            <div class="amt">{{ $money((float) $quote->total_usd) }}</div>
        </div>
        <div class="clear"></div>
    </div>

    @if (filled($quote->notes))
        <div class="notes"><strong>Observaciones.</strong> {{ $quote->notes }}</div>
    @endif

    <div class="foot">
        Cotización informativa, válida por 7 días a partir de la fecha de emisión.
        La disponibilidad se confirma al momento de la compra. Este documento no constituye factura ni comprobante fiscal.
    </div>
</body>
</html>
