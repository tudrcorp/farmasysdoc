<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c0c0f">
    <title>{{ $approved ? 'Caja chica aprobada' : 'Caja chica rechazada' }} · Farmadoc</title>
    <style>
        :root {
            --bg0: #0c0c0f;
            --card: rgba(255, 255, 255, 0.08);
            --card-border: rgba(255, 255, 255, 0.12);
            --text: #f4f4f5;
            --muted: rgba(244, 244, 245, 0.62);
            --radius: 18px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100dvh;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
            background: radial-gradient(120% 80% at 50% -10%, {{ $approved ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.12)' }}, transparent 45%), var(--bg0);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            padding: max(16px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) max(24px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
        }
        .wrap { max-width: 480px; margin: 0 auto; text-align: center; }
        @media (min-width: 768px) { .wrap { max-width: 640px; } }
        .icon {
            width: 64px; height: 64px; margin: 12px auto 16px;
            border-radius: 50%;
            background: {{ $approved ? 'rgba(34, 197, 94, 0.2)' : 'rgba(239, 68, 68, 0.2)' }};
            border: 1px solid {{ $approved ? 'rgba(34, 197, 94, 0.45)' : 'rgba(239, 68, 68, 0.45)' }};
            display: grid; place-items: center;
            font-size: 2rem;
        }
        h1 { font-size: 1.4rem; font-weight: 700; margin: 0 0 8px; letter-spacing: -0.02em; }
        p { color: var(--muted); font-size: 0.95rem; line-height: 1.5; margin: 0 0 22px; }
        .glass {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            padding: 16px;
            margin-bottom: 18px;
            text-align: left;
            font-size: 0.9rem;
        }
        a.btn {
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
            border-radius: 14px;
            padding: 14px 16px;
            font-weight: 700;
            font-size: 1rem;
            color: #0c0c0f;
            background: linear-gradient(135deg, #25d366, #128c7e);
            margin-bottom: 10px;
        }
        a.secondary {
            display: block;
            padding: 12px;
            color: var(--muted);
            font-size: 0.88rem;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="icon">{{ $approved ? '✓' : '✕' }}</div>
        <h1>{{ $approved ? 'Caja chica aprobada' : 'Solicitud rechazada' }}</h1>
        <p>
            @if ($approved)
                El comprobante y la referencia ya quedaron en el sistema. Gerencia puede registrar gastos sobre este fondo.
            @else
                Gerencia podrá volver a solicitar caja chica para esa sucursal.
            @endif
        </p>
        <div class="glass">
            Sucursal: {{ $fund->branch?->name ?? '—' }}<br>
            Solicitó: {{ $fund->requestedBy?->name ?? '—' }}<br>
            @if ($approved && filled($fund->payment_reference))
                Referencia: {{ $fund->payment_reference }}
            @endif
        </div>
        <a class="btn" href="{{ $whatsappReturnUrl }}" rel="noopener">Volver a WhatsApp</a>
        <a class="secondary" href="{{ $panelUrl }}">Ver en Farmaadmin</a>
    </div>
</body>
</html>
