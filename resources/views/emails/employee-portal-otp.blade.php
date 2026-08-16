<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Código del portal</title>
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
            <h1 style="font-size: 18px; margin: 0 0 8px;">Restablecer clave del portal</h1>
            <p style="margin: 0 0 16px; color: #4b5563;">
                Hola {{ $employee->first_name }}, usa este código de 6 dígitos para crear una nueva clave. Caduca en {{ $ttlMinutes }} minutos.
            </p>
            <p style="font-size: 32px; letter-spacing: 0.28em; font-weight: 700; margin: 0 0 16px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; user-select: all;">{{ $otpCode }}</p>
            <p style="margin: 0; color: #6b7280; font-size: 13px;">Si no pediste este código, ignora este correo. No lo compartas.</p>
        </div>
    </div>
</body>
</html>
