<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OTP auditoría de inventario</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.5; color: #111827; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
        <div style="background: #0f172a; padding: 20px 24px; text-align: center;">
            <img
                src="{{ asset('images/logos/farmadoc-ligth.png') }}"
                alt="Farmadoc"
                width="160"
                style="max-width: 160px; height: auto; display: inline-block;"
            >
        </div>

        <div style="padding: 24px;">
            <h1 style="font-size: 18px; margin: 0 0 8px;">OTP — Auditoría de inventario</h1>
            <p style="margin: 0 0 20px; color: #4b5563;">Revise el cambio solicitado antes de compartir la clave OTP con el gerente.</p>

            <p style="margin: 0 0 8px;"><strong>Solicitado por:</strong> {{ $managerName }}</p>
            @if (filled($productName))
                <p style="margin: 0 0 8px;"><strong>Producto:</strong> {{ $productName }}</p>
            @endif
            @if (filled($branchName))
                <p style="margin: 0 0 8px;"><strong>Sucursal:</strong> {{ $branchName }}</p>
            @endif

            <div style="margin: 20px 0; padding: 16px; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 10px;">
                <p style="margin: 0 0 10px; font-size: 13px; color: #9a3412; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600;">Cambios solicitados</p>
                @if (count($changes) > 0)
                    <ul style="margin: 0; padding-left: 18px; color: #7c2d12;">
                        @foreach ($changes as $change)
                            <li style="margin: 0 0 6px;">{{ $change }}</li>
                        @endforeach
                    </ul>
                @else
                    <p style="margin: 0; color: #7c2d12;">Sin detalle de cambios.</p>
                @endif
            </div>

            <p style="margin: 0 0 8px; font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em;">Clave OTP</p>
            <p style="font-size: 32px; letter-spacing: 0.28em; font-weight: 700; margin: 0 0 8px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; user-select: all; -webkit-user-select: all;">{{ $otpCode }}</p>
            <p style="margin: 0 0 20px; font-size: 13px; color: #6b7280;">Selecciona la clave para copiarla. Solo compártala si autoriza el cambio.</p>

            <p style="margin: 20px 0 0; color: #6b7280; font-size: 14px;">
                Código de un solo uso. Caduca en {{ $ttlMinutes }} minutos.
            </p>
        </div>
    </div>
</body>
</html>
