<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OTP conciliación manual Pago Móvil</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.5; color: #111827; margin: 0; padding: 24px; background: #f3f4f6;">
    @php
        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $logoSrc = file_exists($logoPath)
            ? $message->embed($logoPath)
            : asset('images/logos/farmadoc-ligth.png');
        $intro = $fromPosCashier
            ? 'El cajero '.$actorName.' procederá a ejecutar una conciliación manual de Pago Móvil. Entregue la clave OTP al cajero solo si autoriza.'
            : ($actorIsAdministrator
                ? 'El administrador '.$actorName.' procederá a ejecutar una conciliación manual de Pago Móvil.'
                : 'El gerente '.$actorName.' procederá a ejecutar una conciliación manual de Pago Móvil.');
    @endphp
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px; overflow: hidden;">
        <div style="background: #0f172a; padding: 28px 24px; text-align: center;">
            <img
                src="{{ $logoSrc }}"
                alt="Farmadoc"
                width="180"
                style="max-width: 180px; height: auto; display: inline-block;"
            >
        </div>

        <div style="padding: 28px 24px 24px;">
            <h1 style="font-size: 20px; margin: 0 0 10px; color: #0f172a;">OTP — Conciliación manual</h1>
            <p style="margin: 0 0 20px; color: #4b5563;">{{ $intro }}</p>

            <div style="margin: 0 0 24px; padding: 16px 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                <p style="margin: 0 0 10px; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700;">Detalle de la ejecución</p>
                @if (filled($branchName))
                    <p style="margin: 0 0 6px;"><strong>Sucursal:</strong> {{ $branchName }}</p>
                @endif
                @if (filled($reference))
                    <p style="margin: 0 0 6px;"><strong>Referencia:</strong> {{ $reference }}</p>
                @endif
                @if (filled($amount))
                    <p style="margin: 0 0 6px;"><strong>Monto:</strong> {{ $amount }}</p>
                @endif
                @if (filled($payerDocument))
                    <p style="margin: 0 0 6px;"><strong>Doc. pagador:</strong> {{ $payerDocument }}</p>
                @endif
                @if (filled($payerPhone))
                    <p style="margin: 0 0 6px;"><strong>Tel. pagador:</strong> {{ $payerPhone }}</p>
                @endif
                @if (filled($destinationPhone))
                    <p style="margin: 0 0 6px;"><strong>Tel. destino:</strong> {{ $destinationPhone }}</p>
                @endif
                @if (filled($paymentDate))
                    <p style="margin: 0 0 6px;"><strong>Fecha de pago:</strong> {{ $paymentDate }}</p>
                @endif
                @if (filled($originBank))
                    <p style="margin: 0;"><strong>Banco origen:</strong> {{ $originBank }}</p>
                @endif
            </div>

            <div style="margin: 0 0 8px; padding: 22px 16px; background: #fff7ed; border: 2px solid #f59e0b; border-radius: 14px; text-align: center;">
                <p style="margin: 0 0 10px; font-size: 13px; color: #9a3412; text-transform: uppercase; letter-spacing: 0.12em; font-weight: 700;">Clave OTP</p>
                <p style="font-size: 48px; letter-spacing: 0.28em; font-weight: 800; margin: 0; color: #9a3412; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; user-select: all; -webkit-user-select: all; line-height: 1.15;">{{ $otpCode }}</p>
            </div>
            <p style="margin: 0 0 20px; font-size: 13px; color: #6b7280; text-align: center;">Selecciona la clave para copiarla. Es de un solo uso.</p>

            <p style="margin: 0; color: #6b7280; font-size: 14px;">
                Caduca en <strong>{{ $ttlMinutes }} minutos</strong>. Si no solicitó esta conciliación, ignore este correo.
            </p>
        </div>
    </div>
</body>
</html>
