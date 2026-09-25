<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $quote->number }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Hola {{ $quote->requester_name }},</p>
    <p>Adjuntamos la cotización <strong>{{ $quote->number }}</strong> de medicamentos solicitada a {{ config('fiscal.retention_agent.name') }}.</p>
    <p>El total de la cotización es <strong>USD {{ number_format((float) $quote->total_usd, 2, ',', '.') }}</strong>.</p>
    <p>Esta cotización es informativa y está sujeta a la disponibilidad al momento de la compra. Tiene una vigencia de 7 días.</p>
    <p>Si necesita un cambio, responda a este correo o comuníquese con la farmacia.</p>
</body>
</html>
