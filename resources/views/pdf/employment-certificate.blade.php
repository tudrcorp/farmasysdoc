<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 60px 70px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11.5px; line-height: 1.7; }
        .header { text-align: left; border-bottom: 1px solid #0f766e; padding-bottom: 8px; margin-bottom: 40px; }
        .logo { height: 40px; }
        .reference { float: right; font-size: 9px; color: #6b7280; margin-top: 14px; }
        h1 { text-align: center; font-size: 16px; text-transform: uppercase; letter-spacing: 0.12em; margin: 0 0 36px; }
        p.body { text-align: justify; margin: 0 0 16px; }
        .signature { margin-top: 70px; text-align: center; }
        .signature-images { height: 90px; position: relative; }
        .signature-images img { max-height: 80px; max-width: 200px; }
        .stamp { margin-left: 18px; opacity: 0.9; }
        .line { width: 260px; margin: 6px auto 0; border-top: 1px solid #111827; padding-top: 4px; }
        .signer { font-weight: 700; text-transform: uppercase; }
        .signer-title { color: #4b5563; font-size: 10px; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        @if (! empty($logo_data_uri))
            <img class="logo" src="{{ $logo_data_uri }}" alt="Farmadoc">
        @endif
        <span class="reference">{{ $reference }}</span>
    </div>

    <h1>{{ $title }}</h1>

    @foreach ($paragraphs as $paragraph)
        <p class="body">{!! nl2br($paragraph) !!}</p>
    @endforeach

    <div class="signature">
        <div class="signature-images">
            @if (! empty($signature_data_uri))
                <img src="{{ $signature_data_uri }}" alt="Firma">
            @endif
            @if (! empty($stamp_data_uri))
                <img class="stamp" src="{{ $stamp_data_uri }}" alt="Sello">
            @endif
        </div>
        <div class="line">
            <div class="signer">{{ $setting->signer_name }}</div>
            @if (filled($setting->signer_title))
                <div class="signer-title">{{ $setting->signer_title }}</div>
            @endif
        </div>
    </div>

    @if (filled($setting->footer))
        <div class="footer">{{ $setting->footer }}</div>
    @endif
</body>
</html>
