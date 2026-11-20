<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nota de entrega {{ $acceptance->delivery_note_number }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1c2833; font-size: 10pt; margin: 0; }
        .band { background: #0E949A; color: #fff; padding: 16px 18px; }
        .logo { height: 64px; margin-bottom: 10px; }
        .brand { font-size: 15pt; font-weight: 700; }
        .rif { font-size: 8.5pt; margin-top: 3px; }
        .address { font-size: 8pt; margin-top: 4px; line-height: 1.35; }
        .doc { float: right; text-align: right; }
        .doc .k { font-size: 8pt; letter-spacing: 1px; text-transform: uppercase; }
        .doc .n { font-size: 13pt; font-weight: 700; margin-top: 2px; }
        .clear { clear: both; }
        h1 { font-size: 16pt; margin: 18px 0 4px; color: #0E949A; }
        h2 { font-size: 12pt; margin: 16px 0 6px; color: #12343b; }
        .lead { margin: 0 0 14px; color: #52616b; font-size: 9.5pt; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 14px; }
        .card { border: 1px solid #d5e3e4; background: #f4fbfb; padding: 10px 12px; vertical-align: top; width: 50%; }
        .card .label { font-size: 7.5pt; letter-spacing: 0.6px; text-transform: uppercase; color: #0E949A; font-weight: 700; }
        .card .name { font-size: 12pt; font-weight: 700; margin: 2px 0 4px; }
        .card .line { font-size: 9pt; margin-top: 1px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #12343b; color: #fff; font-size: 8pt; text-align: left; padding: 7px 8px; }
        table.items td { border-bottom: 1px solid #e4ecec; padding: 7px 8px; font-size: 9pt; vertical-align: top; }
        table.items tr:nth-child(even) td { background: #f7fbfb; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #7a8a92; font-size: 8pt; }
        .note { color: #7b341e; font-size: 8.5pt; margin-top: 3px; }
        .totals { margin-top: 12px; width: 100%; }
        .total-box { background: #0E949A; color: #fff; padding: 10px 14px; }
        .total-box .lbl { font-size: 8pt; letter-spacing: 0.8px; text-transform: uppercase; }
        .total-box .amt { font-size: 14pt; font-weight: 700; margin-top: 2px; }
        .foot { margin-top: 22px; font-size: 7.5pt; color: #7a8a92; line-height: 1.45; }
    </style>
</head>
<body>
    @php
        $money = static fn (mixed $amount): string => 'USD '.number_format((float) $amount, 2, ',', '.');
        $bs = static fn (mixed $amount): string => 'Bs. '.number_format((float) $amount, 2, ',', '.');
        $qty = static fn (mixed $amount): string => rtrim(rtrim(number_format((float) $amount, 3, ',', '.'), '0'), ',');
        $acceptedAt = $acceptance->accepted_at?->timezone(config('app.timezone'));
        $partner = $acceptance->partnerCompany ?? $acceptance->order?->partnerCompany;
        $acceptedItems = $acceptance->items->where('accepted', true)->values();
        $rejectedItems = $acceptance->items->where('accepted', false)->values();
    @endphp

    <div class="band">
        <div class="doc">
            <div class="k">Nota de entrega</div>
            <div class="n">{{ $acceptance->delivery_note_number }}</div>
            <div>{{ $acceptedAt?->format('d/m/Y h:i a') }}</div>
        </div>
        @if (! empty($pdf_logo_data_uri))
            <img class="logo" src="{{ $pdf_logo_data_uri }}" alt="Farmadoc">
        @endif
        <div class="brand">{{ $company_name }}</div>
        <div class="rif">RIF {{ $company_rif }}</div>
        <div class="address">{{ $company_address }}</div>
        <div class="clear"></div>
    </div>

    <h1>Nota de entrega al aliado</h1>
    <p class="lead">Despacho de los medicamentos aceptados del pedido {{ $acceptance->order?->order_number }}. Este documento no es una venta ni un comprobante fiscal.</p>

    <table class="cards">
        <tr>
            <td class="card">
                <div class="label">Aliado</div>
                <div class="name">{{ $partner?->trade_name ?: $partner?->legal_name ?: '—' }}</div>
                <div class="line"><strong>Código:</strong> {{ $partner?->code ?: '—' }}</div>
                <div class="line"><strong>Pedido:</strong> {{ $acceptance->order?->order_number ?: '—' }}</div>
            </td>
            <td class="card" style="border-left: 0;">
                <div class="label">Decisión</div>
                <div class="name">{{ $acceptance->accepted_by_name ?: '—' }}</div>
                <div class="line"><strong>Tasa BCV:</strong> {{ number_format((float) $acceptance->bcv_ves_per_usd, 2, ',', '.') }} Bs. por USD</div>
                <div class="line"><strong>Aceptado el:</strong> {{ $acceptedAt?->format('d/m/Y h:i a') ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <h2>Productos que se envían</h2>
    <table class="items">
        <thead>
            <tr>
                <th>Medicamento</th>
                <th class="num">Cantidad</th>
                <th class="num">Precio USD</th>
                <th class="num">Total USD</th>
                <th class="num">Total Bs.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($acceptedItems as $item)
                <tr>
                    <td>
                        {{ $item->product_name }}
                        <div class="muted">{{ $item->barcode ?: 'Sin código de barra' }}@if ($item->sku) · SKU {{ $item->sku }}@endif</div>
                    </td>
                    <td class="num">{{ $qty($item->quantity_ordered) }}</td>
                    <td class="num">{{ $money($item->unit_price_usd) }}</td>
                    <td class="num">{{ $money($item->line_total_usd) }}</td>
                    <td class="num">{{ $bs($item->line_total_ves) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Ningún medicamento fue aceptado en este pedido.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td></td>
            <td style="width: 46%;">
                <div class="total-box">
                    <div class="lbl">Total aceptado</div>
                    <div class="amt">{{ $money($acceptance->accepted_total_usd) }}</div>
                    <div>{{ $bs($acceptance->accepted_total_ves) }} · tasa BCV {{ number_format((float) $acceptance->bcv_ves_per_usd, 2, ',', '.') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <h2>Productos que no se envían</h2>
    <table class="items">
        <thead>
            <tr>
                <th>Medicamento</th>
                <th class="num">Pedido</th>
                <th class="num">Existencia</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rejectedItems as $item)
                <tr>
                    <td>
                        {{ $item->product_name }}
                        <div class="muted">{{ $item->barcode ?: 'Sin código de barra' }}</div>
                    </td>
                    <td class="num">{{ $qty($item->quantity_ordered) }}</td>
                    <td class="num">{{ $qty($item->quantity_on_hand) }}</td>
                    <td>{{ $item->rejection_note ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Todos los medicamentos del pedido fueron aceptados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="foot">
        La existencia descontada corresponde al inventario principal de Las Delicias y solo incluye los productos aceptados.
        Los precios quedaron congelados a la tasa BCV del momento de la aceptación. Esta nota no genera una venta.
    </p>
</body>
</html>
