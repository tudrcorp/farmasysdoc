<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nota de entrega {{ $sale->number }}</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.5; color: #111827; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
        <div style="background: #0E949A; padding: 18px 24px; color: #ffffff;">
            <p style="margin: 0; font-size: 12px; letter-spacing: 1px; text-transform: uppercase;">Nota de entrega</p>
            <p style="margin: 2px 0 0; font-size: 20px; font-weight: 700;">{{ $sale->number }}</p>
        </div>
        <div style="padding: 24px;">
            <p style="margin: 0 0 12px;">Hola {{ $sale->client_name }},</p>
            <p style="margin: 0 0 16px; color: #4b5563;">Adjuntamos la nota de entrega de su compra del {{ $sale->sold_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a') }} por <strong>USD {{ number_format((float) $sale->total, 2, ',', '.') }}</strong>, con cargo a su convenio {{ $sale->partnerCompany?->trade_name ?: $sale->partnerCompany?->legal_name }}.</p>
            <p style="margin: 0; color: #6b7280; font-size: 13px;">Forma de pago: Por convenio. Este documento no es una factura.</p>
        </div>
    </div>
</body>
</html>
