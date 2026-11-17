<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura comercial {{ $document_number }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1c2428; font-size: 10pt; margin: 0; }
        .band { background: #0E3A3F; color: #fff; padding: 14px 16px; }
        .brand { font-size: 14pt; font-weight: 700; }
        .rif, .address { font-size: 8pt; margin-top: 3px; line-height: 1.35; }
        .doc { float: right; text-align: right; }
        .doc .k { font-size: 8pt; letter-spacing: 0.8px; text-transform: uppercase; color: #9ad7db; }
        .doc .n { font-size: 13pt; font-weight: 700; margin-top: 2px; }
        .clear { clear: both; }
        .notice { margin-top: 12px; background: #F7F3EA; border: 1px solid #C4A35A; color: #6d5424; padding: 8px 10px; font-size: 8.5pt; }
        h1 { font-size: 15pt; margin: 14px 0 2px; color: #0E3A3F; }
        .lead { margin: 0 0 12px; color: #5E6A70; font-size: 9pt; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.meta td { width: 50%; vertical-align: top; border: 1px solid #D5DEE2; padding: 8px 10px; }
        .label { font-size: 7.5pt; letter-spacing: 0.5px; text-transform: uppercase; color: #0E949A; font-weight: 700; }
        .name { font-size: 12pt; font-weight: 700; margin-top: 2px; }
        .line { font-size: 9pt; margin-top: 2px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #0E3A3F; color: #fff; font-size: 8pt; text-align: left; padding: 6px 7px; }
        table.items td { border-bottom: 1px solid #E4ECEC; padding: 6px 7px; font-size: 9pt; }
        table.items tr:nth-child(even) td { background: #F3F7F8; }
        .num { text-align: right; white-space: nowrap; }
        table.totals { width: 250px; margin-left: auto; margin-top: 12px; border-collapse: collapse; }
        table.totals td { padding: 4px 8px; font-size: 9pt; }
        table.totals .due td { background: #0E949A; color: #fff; font-weight: 700; font-size: 11pt; }
        .notes { margin-top: 14px; font-size: 9pt; }
        .foot { margin-top: 16px; font-size: 8pt; color: #5E6A70; line-height: 1.45; }
    </style>
</head>
<body>
    @php
        $money = static fn (float $amount): string => 'USD '.number_format($amount, 2, ',', '.');
        $bolivares = static fn (float $amount): string => 'Bs. '.number_format($amount, 2, ',', '.');
        $qty = static fn (float $amount): string => rtrim(rtrim(number_format($amount, 3, ',', '.'), '0'), ',');
    @endphp

    <div class="band">
        <div class="doc">
            <div class="k">Factura comercial</div>
            <div class="n">{{ $document_number }}</div>
            <div>{{ $issued_at }}</div>
        </div>
        @if ($pdf_logo_data_uri)
            <img src="{{ $pdf_logo_data_uri }}" alt="Farmadoc" style="height: 28px; margin-bottom: 6px;">
        @endif
        <div class="brand">{{ $company_name }}</div>
        <div class="rif">RIF {{ $company_rif }}</div>
        @if (filled($company_address))
            <div class="address">{{ $company_address }}</div>
        @endif
        <div class="clear"></div>
    </div>

    <div class="notice">
        Documento comercial interno. Sirve para entregar al cliente el detalle de la venta a crédito y el saldo de la cuenta por cobrar. No es factura fiscal ni comprobante del SENIAT.
    </div>

    <h1>Factura</h1>
    <p class="lead">Estado: {{ $status_label }}. Sucursal {{ $branch_name }}.</p>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Cliente</div>
                <div class="name">{{ $client_name }}</div>
                <div class="line">Documento: {{ $client_document }}</div>
            </td>
            <td>
                <div class="label">Condiciones</div>
                <div class="line"><strong>Emisión:</strong> {{ $issued_at }}</div>
                <div class="line"><strong>Vencimiento:</strong> {{ $due_at }}</div>
                <div class="line"><strong>Forma de pago:</strong> {{ $payment_method }}</div>
                <div class="line"><strong>Estado:</strong> {{ $status_label }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Descripción</th>
                <th>Código</th>
                <th class="num">Cantidad</th>
                <th class="num">Precio</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['description'] }}</td>
                    <td>{{ $line['sku'] }}</td>
                    <td class="num">{{ $qty((float) $line['quantity']) }}</td>
                    <td class="num">{{ $money((float) $line['unit_price']) }}</td>
                    <td class="num">{{ $money((float) $line['line_total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="num">{{ $money($subtotal_usd) }}</td>
        </tr>
        @if ($discount_usd > 0)
            <tr>
                <td>Descuento</td>
                <td class="num">{{ $money($discount_usd) }}</td>
            </tr>
        @endif
        @if ($tax_usd > 0)
            <tr>
                <td>IVA</td>
                <td class="num">{{ $money($tax_usd) }}</td>
            </tr>
        @endif
        @if ($igtf_usd > 0)
            <tr>
                <td>IGTF</td>
                <td class="num">{{ $money($igtf_usd) }}</td>
            </tr>
        @endif
        <tr>
            <td>Total</td>
            <td class="num">{{ $money($total_usd) }}</td>
        </tr>
        <tr>
            <td>Cobrado</td>
            <td class="num">{{ $money($paid_usd) }}</td>
        </tr>
        <tr class="due">
            <td>Saldo pendiente</td>
            <td class="num">{{ $money($remaining_usd) }}</td>
        </tr>
        @if ($balance_ves > 0)
            <tr>
                <td>Saldo en bolívares</td>
                <td class="num">{{ $bolivares($balance_ves) }}</td>
            </tr>
        @endif
        @if ($bcv_rate)
            <tr>
                <td>Tasa BCV de la venta</td>
                <td class="num">{{ number_format($bcv_rate, 4, ',', '.') }}</td>
            </tr>
        @endif
    </table>

    @if (filled($notes))
        <div class="notes"><strong>Notas.</strong> {{ $notes }}</div>
    @endif

    <div class="foot">
        Generada el {{ $generated_at }} por {{ $generated_by }}.
        @if ($total_ves > 0)
            Referencia de la venta en bolívares: {{ $bolivares($total_ves) }}.
        @endif
        Este documento no acredita el pago ni reemplaza el comprobante fiscal de la venta.
    </div>
</body>
</html>
