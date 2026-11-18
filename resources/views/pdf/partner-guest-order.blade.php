<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pedido {{ $order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #10292d; font-size: 10pt; margin: 0; }
        .band { background: #0e3a3f; color: #fff; padding: 14px 16px; }
        .brand { font-size: 14pt; font-weight: 700; }
        .doc { float: right; text-align: right; }
        .doc .k { font-size: 8pt; letter-spacing: 1px; text-transform: uppercase; }
        .doc .n { font-size: 13pt; font-weight: 700; margin-top: 2px; }
        .clear { clear: both; }
        .logo { height: 36px; margin-bottom: 6px; }
        h1 { font-size: 15pt; margin: 16px 0 4px; color: #0e949a; }
        .lead { margin: 0 0 12px; color: #5d7176; font-size: 9pt; }
        .card { border: 1px solid #d5e3e4; background: #f4fbfb; padding: 10px 12px; margin-bottom: 12px; }
        .label { font-size: 7.5pt; letter-spacing: 0.6px; text-transform: uppercase; color: #0e949a; font-weight: 700; }
        .name { font-size: 12pt; font-weight: 700; margin: 2px 0 4px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #12343b; color: #fff; font-size: 8pt; text-align: left; padding: 6px; }
        table.items td { border-bottom: 1px solid #e4ecec; padding: 6px; font-size: 9pt; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-top: 10px; width: 100%; }
        .totals td { font-size: 9.5pt; padding: 2px 0; }
        .grand { font-size: 13pt; font-weight: 700; color: #0e3a3f; }
        .foot { margin-top: 16px; font-size: 8pt; color: #607078; }
    </style>
</head>
<body>
    @php
        $money = static fn (float $amount): string => 'USD '.number_format($amount, 2, ',', '.');
        $qty = static fn (float $amount): string => rtrim(rtrim(number_format($amount, 3, ',', '.'), '0'), ',');
    @endphp
    <div class="band">
        <div class="doc">
            <div class="k">Pedido</div>
            <div class="n">{{ $order_number }}</div>
            <div>{{ $issued_at }}</div>
            <div>{{ $status_label }}</div>
        </div>
        @if ($pdf_logo_data_uri)
            <img class="logo" src="{{ $pdf_logo_data_uri }}" alt="Farmadoc">
        @endif
        <div class="brand">{{ $company_name }}</div>
        <div>RIF {{ $company_rif }}</div>
        <div>{{ $company_address }}</div>
        <div class="clear"></div>
    </div>

    <h1>Detalle del pedido</h1>
    <p class="lead">Documento del pedido. No es factura fiscal ni comprobante del SENIAT.</p>

    <div class="card">
        <div class="label">Aliado</div>
        <div class="name">{{ $ally_name }}</div>
        <div>Código {{ $ally_code }} · Sucursal {{ $branch_name }}</div>
        <div>Entrega: {{ $fulfillment_label }} · Pago: {{ $payment_label }}@if ($cash_method_label) · {{ $cash_method_label }}@endif</div>
        @if ($order->delivery_recipient_name)
            <div style="margin-top: 6px;">Recibe: {{ $order->delivery_recipient_name }} · {{ $order->delivery_phone }}</div>
        @endif
        @if ($order->delivery_address)
            <div>{{ $order->delivery_address }}@if ($order->delivery_city), {{ $order->delivery_city }}@endif @if ($order->delivery_state) · {{ $order->delivery_state }}@endif</div>
        @endif
        @if ($order->delivery_notes)
            <div>Indicaciones: {{ $order->delivery_notes }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Código</th>
                <th>Principio activo</th>
                <th class="num">Cant.</th>
                <th class="num">Precio</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['name'] }}</td>
                    <td>{{ $line['code'] }}</td>
                    <td>{{ $line['ingredient'] !== '' ? $line['ingredient'] : '—' }}</td>
                    <td class="num">{{ $qty($line['quantity']) }}</td>
                    <td class="num">{{ $money($line['unit_price']) }}</td>
                    <td class="num">{{ $money($line['line_total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td></td>
            <td class="num" style="width: 180px;">Impuesto</td>
            <td class="num" style="width: 120px;">{{ $money($tax_total) }}</td>
        </tr>
        <tr>
            <td></td>
            <td class="num">Descuento</td>
            <td class="num">{{ $money($discount_total) }}</td>
        </tr>
        <tr>
            <td></td>
            <td class="num grand">Total</td>
            <td class="num grand">{{ $money($total) }}</td>
        </tr>
    </table>

    <p class="foot">Farmadoc · {{ $branch_name }}. Conserve este comprobante como detalle del pedido {{ $order_number }}.</p>
</body>
</html>
