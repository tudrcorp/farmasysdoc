<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nota de entrega {{ $sale->number }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1c2833; font-size: 10pt; margin: 0; }
        .band { background: #0E949A; color: #fff; padding: 16px 18px; }
        .brand { font-size: 15pt; font-weight: 700; }
        .rif { font-size: 8.5pt; margin-top: 3px; }
        .address { font-size: 8pt; margin-top: 4px; line-height: 1.35; }
        .doc { float: right; text-align: right; }
        .doc .k { font-size: 8pt; letter-spacing: 1px; text-transform: uppercase; }
        .doc .n { font-size: 13pt; font-weight: 700; margin-top: 2px; }
        .clear { clear: both; }
        h1 { font-size: 16pt; margin: 18px 0 4px; color: #0E949A; }
        .lead { margin: 0 0 14px; color: #52616b; font-size: 9.5pt; }
        .cancelled { border: 2px solid #c0392b; color: #c0392b; padding: 8px 12px; font-weight: 700; margin-bottom: 12px; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 14px; }
        .card { border: 1px solid #d5e3e4; background: #f4fbfb; padding: 10px 12px; vertical-align: top; width: 50%; }
        .card .label { font-size: 7.5pt; letter-spacing: 0.6px; text-transform: uppercase; color: #0E949A; font-weight: 700; }
        .card .name { font-size: 12pt; font-weight: 700; margin: 2px 0 4px; }
        .card .line { font-size: 9pt; margin-top: 1px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #12343b; color: #fff; font-size: 8pt; text-align: left; padding: 7px 8px; }
        table.items td { border-bottom: 1px solid #e4ecec; padding: 7px 8px; font-size: 9.5pt; }
        table.items tr:nth-child(even) td { background: #f7fbfb; }
        .num { text-align: right; white-space: nowrap; }
        .sku { color: #7a8a92; font-size: 8pt; }
        .totals { margin-top: 12px; width: 100%; }
        .totals td { padding: 3px 0; font-size: 9.5pt; }
        .total-box { background: #0E949A; color: #fff; padding: 10px 14px; }
        .total-box .lbl { font-size: 8pt; letter-spacing: 0.8px; text-transform: uppercase; }
        .total-box .amt { font-size: 16pt; font-weight: 700; margin-top: 2px; }
        .pay { margin-top: 14px; border: 1px dashed #0E949A; padding: 8px 12px; font-size: 9.5pt; }
        .signs { margin-top: 40px; width: 100%; }
        .signs td { width: 50%; padding: 0 18px; text-align: center; font-size: 8.5pt; color: #52616b; }
        .signs .rule { border-top: 1px solid #9fb1b6; padding-top: 4px; }
        .foot { margin-top: 22px; font-size: 7.5pt; color: #7a8a92; line-height: 1.45; }
    </style>
</head>
<body>
    @php
        $money = static fn (mixed $amount): string => 'USD '.number_format((float) $amount, 2, ',', '.');
        $bs = static fn (mixed $amount): string => 'Bs. '.number_format((float) $amount, 2, ',', '.');
        $qty = static fn (mixed $amount): string => rtrim(rtrim(number_format((float) $amount, 3, ',', '.'), '0'), ',');
        $soldAt = $sale->sold_at?->timezone(config('app.timezone'));
    @endphp

    <div class="band">
        <div class="doc">
            <div class="k">Nota de entrega</div>
            <div class="n">{{ $sale->number }}</div>
            <div>{{ $soldAt?->format('d/m/Y h:i a') }}</div>
        </div>
        <div class="brand">{{ $company_name }}</div>
        <div class="rif">RIF {{ $company_rif }}</div>
        <div class="address">{{ $company_address }}</div>
        <div class="clear"></div>
    </div>

    <h1>Nota de entrega por convenio</h1>
    <p class="lead">Documento no fiscal. Despacho a cargo del convenio {{ $sale->partnerCompany?->trade_name ?: $sale->partnerCompany?->legal_name }} · {{ $sale->plan?->name }}.</p>

    @if ($sale->isCancelled())
        <div class="cancelled">ANULADA el {{ $sale->cancelled_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a') }} · {{ $sale->cancellation_reason }}</div>
    @endif

    <table class="cards">
        <tr>
            <td class="card">
                <div class="label">Afiliado</div>
                <div class="name">{{ $sale->client_name }}</div>
                <div class="line"><strong>Cédula:</strong> {{ $sale->client_document ?: '—' }}</div>
                <div class="line"><strong>Teléfono:</strong> {{ $sale->client_phone ?: '—' }}</div>
                <div class="line"><strong>Correo:</strong> {{ $sale->client_email ?: '—' }}</div>
            </td>
            <td class="card" style="border-left: 0;">
                <div class="label">Convenio</div>
                <div class="name">{{ $sale->partnerCompany?->legal_name }}</div>
                <div class="line"><strong>RIF:</strong> {{ $sale->partnerCompany?->tax_id ?: '—' }}</div>
                <div class="line"><strong>Plan:</strong> {{ $sale->plan?->name }}</div>
                <div class="line"><strong>Sucursal:</strong> {{ $sale->branch?->name }}</div>
                <div class="line"><strong>Atendido por:</strong> {{ $sale->seller?->name ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Producto</th>
                <th class="num" style="width: 12%;">Cantidad</th>
                <th class="num" style="width: 17%;">Precio unit.</th>
                <th class="num" style="width: 13%;">IVA</th>
                <th class="num" style="width: 17%;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->product_name_snapshot }} @if ($item->sku_snapshot)<span class="sku">· {{ $item->sku_snapshot }}</span>@endif</td>
                    <td class="num">{{ $qty($item->quantity) }}</td>
                    <td class="num">{{ $money($item->unit_price) }}</td>
                    <td class="num">{{ $money($item->tax_amount) }}</td>
                    <td class="num">{{ $money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="pay"><strong>Forma de pago:</strong> {{ $payment_label }}</div>
                @if ($sale->bcv_ves_per_usd)
                    <div class="foot" style="margin-top: 8px;">Tasa BCV: {{ number_format((float) $sale->bcv_ves_per_usd, 2, ',', '.') }} Bs. por USD · Equivalente {{ $bs($sale->total_ves) }}</div>
                @endif
                @if ($sale->notes)
                    <div class="foot" style="margin-top: 6px;"><strong>Observaciones:</strong> {{ $sale->notes }}</div>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table style="width: 100%;">
                    <tr><td>Subtotal</td><td class="num">{{ $money($sale->subtotal) }}</td></tr>
                    <tr><td>IVA</td><td class="num">{{ $money($sale->tax_total) }}</td></tr>
                </table>
                <div class="total-box">
                    <div class="lbl">Total</div>
                    <div class="amt">{{ $money($sale->total) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="signs">
        <tr>
            <td><div class="rule">Entregado por</div></td>
            <td><div class="rule">Recibido conforme · {{ $sale->client_name }}</div></td>
        </tr>
    </table>

    <div class="foot">Esta nota de entrega no es una factura. El monto se descuenta del fondo de consumo del convenio. Generada por Farmadoc el {{ now()->timezone(config('app.timezone'))->format('d/m/Y h:i a') }}.</div>
</body>
</html>
