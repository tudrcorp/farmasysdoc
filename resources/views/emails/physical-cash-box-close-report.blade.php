<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Cierre de caja física</title>
</head>
<body style="margin:0; padding:0; background-color:#eef5f5; font-family:Arial, Helvetica, sans-serif; color:#1a1a1a;">
    @php
        $logoSrc = (isset($message) && is_readable($logoPath))
            ? $message->embed($logoPath)
            : '';
    @endphp
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#eef5f5; margin:0; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #d7e6e6;">
                    <tr>
                        <td align="center" style="background-color:#ffffff; padding:28px 24px 18px 24px; border-bottom:1px solid #e4efef;">
                            @if ($logoSrc !== '')
                                <img src="{{ $logoSrc }}" alt="{{ $appName }}" width="240" style="display:block; max-width:240px; width:240px; height:auto; border:0; margin:0 auto;">
                            @else
                                <p style="margin:0; color:#0e5c5f; font-size:28px; font-weight:700;">{{ $appName }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color:#0e5c5f; padding:14px 24px;">
                            <p style="margin:0; color:#d7f3f4; font-size:12px; letter-spacing:1.6px; text-transform:uppercase; font-weight:700;">Cierre de caja física</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 28px 8px 28px;">
                            <span style="display:inline-block; background-color:{{ $hasMismatch ? '#fee2e2' : '#e6f6e8' }}; color:{{ $hasMismatch ? '#b91c1c' : '#166534' }}; font-size:12px; font-weight:700; letter-spacing:0.4px; text-transform:uppercase; padding:6px 12px; border-radius:999px;">
                                {{ $statusLabel }}
                            </span>
                            <h1 style="margin:14px 0 8px 0; font-size:24px; line-height:1.25; color:#0e5c5f;">Reporte comparativo de cierre</h1>
                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.55; color:#4b5c5c;">
                                El cajero <strong style="color:#1a1a1a;">{{ $cashierName }}</strong> cerró caja en
                                <strong style="color:#1a1a1a;">{{ $branchName }}</strong>.
                                El detalle completo va en el PDF adjunto. Si este correo no llega, el reporte queda guardado en el panel.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 12px 28px;">
                            <p style="margin:0 0 10px 0; font-size:13px; font-weight:700; color:#0e5c5f; letter-spacing:0.4px; text-transform:uppercase;">Turno</p>
                            <p style="margin:0 0 16px 0; font-size:14px; color:#4b5c5c;">Apertura {{ $openedAtLabel }} · Cierre {{ $closedAtLabel }}</p>
                            <p style="margin:0 0 10px 0; font-size:13px; font-weight:700; color:#0e5c5f; letter-spacing:0.4px; text-transform:uppercase;">Efectivo en caja física</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #d7e6e6; border-radius:12px; overflow:hidden; margin-bottom:16px;">
                                @foreach ([
                                    ['USD sistema', $expectedUsdLabel],
                                    ['USD declarado', $declaredUsdLabel],
                                    ['USD diferencia', $differenceUsdLabel.' · '.$usdStatusLabel],
                                    ['VES sistema', $expectedVesLabel],
                                    ['VES declarado', $declaredVesLabel],
                                    ['VES diferencia', $differenceVesLabel.' · '.$vesStatusLabel],
                                ] as $index => $row)
                                    <tr>
                                        <td width="42%" valign="top" style="padding:11px 14px; background-color:{{ $index % 2 === 0 ? '#f7fbfb' : '#ffffff' }}; font-size:13px; color:#5b6b6b; border-bottom:1px solid #e4efef;">
                                            {{ $row[0] }}
                                        </td>
                                        <td valign="top" style="padding:11px 14px; background-color:{{ $index % 2 === 0 ? '#f7fbfb' : '#ffffff' }}; font-size:13px; color:{{ str_contains($row[0], 'diferencia') && ! str_contains($row[1], 'Cuadrado') ? '#b91c1c' : '#1a1a1a' }}; font-weight:{{ str_contains($row[0], 'diferencia') ? '700' : '400' }}; border-bottom:1px solid #e4efef;">
                                            {{ $row[1] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                            <p style="margin:0 0 10px 0; font-size:13px; font-weight:700; color:#0e5c5f; letter-spacing:0.4px; text-transform:uppercase;">Punto de venta por banco</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #d7e6e6; border-radius:12px; overflow:hidden;">
                                @forelse ($posLines as $index => $line)
                                    <tr>
                                        <td valign="top" style="padding:11px 14px; background-color:{{ $index % 2 === 0 ? '#f7fbfb' : '#ffffff' }}; font-size:13px; color:#1a1a1a; border-bottom:1px solid #e4efef;">
                                            <strong>{{ $line['bank_label'] }}</strong><br>
                                            Sistema Bs. {{ number_format((float) $line['system_ves'], 2, ',', '.') }}
                                            · Declarado Bs. {{ number_format((float) $line['declared_ves'], 2, ',', '.') }}
                                            · <span style="color:{{ abs((float) $line['difference_ves']) >= 0.01 ? '#b91c1c' : '#166534' }}; font-weight:700;">{{ $line['status_label'] }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td style="padding:11px 14px; font-size:13px; color:#5b6b6b;">Sin declaraciones ni cobros de punto de venta.</td>
                                    </tr>
                                @endforelse
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 28px 28px;">
                            <p style="margin:0; font-size:12px; color:#6b7c7c;">Adjunto: {{ $pdfFilename }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
