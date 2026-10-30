<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Certificado de capacitación</title>
    <style>
        @page { margin: 44px 64px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; line-height: 1.6; text-align: center; }
        .header { text-align: left; border-bottom: 1px solid #0f766e; padding-bottom: 8px; margin-bottom: 28px; }
        .logo { height: 40px; }
        .reference { float: right; font-size: 9px; color: #6b7280; margin-top: 14px; }
        h1 { font-size: 22px; text-transform: uppercase; letter-spacing: 0.14em; margin: 0 0 18px; color: #0f766e; }
        .name { font-size: 20px; font-weight: 700; margin: 8px 0 2px; }
        .id { color: #4b5563; margin: 0 0 16px; }
        .course { font-size: 15px; font-weight: 700; margin: 8px 0; }
        .details { color: #374151; margin: 0; }
        .signature { margin-top: 18px; }
        .signature-images { height: 80px; }
        .signature-images img { max-height: 76px; max-width: 200px; }
        .line { width: 260px; margin: 2px auto 0; border-top: 1px solid #111827; padding-top: 4px; }
        .signer { font-weight: 700; text-transform: uppercase; }
        .signer-title { color: #4b5563; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        @if (! empty($logo_data_uri))
            <img class="logo" src="{{ $logo_data_uri }}" alt="Farmadoc">
        @endif
        <span class="reference">{{ $reference }}</span>
    </div>

    <h1>Certificado</h1>
    <p class="details">Se certifica que</p>
    <p class="name">{{ $employee_name }}</p>
    <p class="id">Cédula de identidad N.° {{ $national_id }}</p>
    <p class="details">completó satisfactoriamente {{ $course_type }}</p>
    <p class="course">{{ $course_name }}</p>
    <p class="details">
        con una duración de {{ $duration_label }}, finalizado el {{ $date_label }}@if (filled($facilitator)), dictado por {{ $facilitator }}@endif.
    </p>
    @if (filled($expires_on))
        <p class="details">Vigente hasta el {{ $expires_on }}.</p>
    @endif

    <div class="signature">
        <div class="signature-images">
            @if (! empty($signature_data_uri))
                <img src="{{ $signature_data_uri }}" alt="Firma">
            @endif
            @if (! empty($stamp_data_uri))
                <img src="{{ $stamp_data_uri }}" alt="Sello">
            @endif
        </div>
        <div class="line">
            <div class="signer">{{ $setting->signer_name }}</div>
            @if (filled($setting->signer_title))
                <div class="signer-title">{{ $setting->signer_title }}</div>
            @endif
        </div>
    </div>
</body>
</html>
