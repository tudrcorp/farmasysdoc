<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo de nómina</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.5; color: #111827; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
        <div style="background: #0f172a; padding: 20px 24px; text-align: center;">
            <img
                src="{{ asset('images/logos/farmadoc-ligth.png') }}"
                alt="Farmadoc"
                width="160"
                style="max-width: 160px; height: auto; display: inline-block;"
            >
        </div>
        <div style="padding: 24px;">
            <h1 style="font-size: 18px; margin: 0 0 8px;">Recibo de nómina</h1>
            <p style="margin: 0 0 16px; color: #4b5563;">
                Adjuntamos el recibo mensual de pago de ley correspondiente a
                <strong>{{ $receipt->periodLabel() }}</strong>.
            </p>
            <p style="margin: 0 0 8px;"><strong>Trabajador:</strong> {{ $receipt->worker_name }}</p>
            <p style="margin: 0 0 8px;"><strong>C.I.:</strong> {{ $receipt->national_id ?: '—' }}</p>
            <p style="margin: 0 0 16px;"><strong>Total a pagar:</strong> Bs {{ number_format((float) $receipt->total_ves, 2, ',', '.') }}</p>
            <p style="margin: 0; color: #6b7280; font-size: 13px;">El detalle de sueldo de ley, asignaciones y deducciones está en el PDF adjunto.</p>
        </div>
    </div>
</body>
</html>
