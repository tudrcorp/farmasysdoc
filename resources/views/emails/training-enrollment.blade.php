<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $headline }}</title>
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
            <h1 style="font-size: 18px; margin: 0 0 8px;">{{ $headline }}</h1>
            <p style="margin: 0 0 16px; color: #4b5563;">Hola, {{ $enrollment->employee->first_name }}.</p>

            <p style="margin: 0 0 8px;"><strong>Fecha:</strong> {{ $session->scheduleLabel() }}</p>
            <p style="margin: 0 0 8px;"><strong>Modalidad:</strong> {{ $session->modality->label() }}</p>
            @if (filled($session->location))
                <p style="margin: 0 0 8px;"><strong>Lugar:</strong> {{ $session->location }}</p>
            @endif
            @if (filled($session->meeting_url))
                <p style="margin: 0 0 8px;"><strong>Enlace:</strong> <a href="{{ $session->meeting_url }}">{{ $session->meeting_url }}</a></p>
            @endif

            <p style="margin: 20px 0 0; text-align: center;">
                <a href="{{ $portalUrl }}" style="display: inline-block; background: #0e7490; color: #ffffff; text-decoration: none; font-weight: 700; padding: 10px 20px; border-radius: 10px;">
                    Ver en el portal
                </a>
            </p>
        </div>
    </div>
</body>
</html>
