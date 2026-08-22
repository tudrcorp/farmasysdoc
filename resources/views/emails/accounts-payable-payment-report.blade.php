<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Comprobante de pago Farmadoc</title>
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
                                <img src="{{ $logoSrc }}" alt="Farmadoc" width="240" style="display:block; max-width:240px; width:240px; height:auto; border:0; margin:0 auto;">
                            @else
                                <p style="margin:0; color:#0e5c5f; font-size:28px; font-weight:700; letter-spacing:0.5px;">farmadoc</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color:#0e5c5f; padding:14px 24px;">
                            <p style="margin:0; color:#d7f3f4; font-size:12px; letter-spacing:1.6px; text-transform:uppercase; font-weight:700;">Comprobante de pago a proveedor</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 28px 8px 28px;">
                            <span style="display:inline-block; background-color:#e6f6e8; color:#166534; font-size:12px; font-weight:700; letter-spacing:0.4px; text-transform:uppercase; padding:6px 12px; border-radius:999px;">
                                {{ $statusLabel }}
                            </span>
                            <h1 style="margin:14px 0 8px 0; font-size:24px; line-height:1.25; color:#0e5c5f;">Pago registrado a su favor</h1>
                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.55; color:#4b5c5c;">
                                Farmadoc registró un pago asociado a su factura
                                <strong style="color:#1a1a1a;">{{ $invoiceNumber }}</strong>.
                                El detalle completo va en el PDF adjunto.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 20px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f0fafb; border:1px solid #b7d9db; border-radius:12px;">
                                <tr>
                                    <td width="50%" valign="top" style="padding:16px 18px; border-right:1px solid #cfe6e7;">
                                        <p style="margin:0 0 4px 0; font-size:11px; letter-spacing:0.8px; text-transform:uppercase; color:#0e5c5f; font-weight:700;">Total pagado USD</p>
                                        <p style="margin:0; font-size:22px; line-height:1.2; color:#0a4648; font-weight:700;">{{ $amountPaidUsdLabel }}</p>
                                    </td>
                                    <td width="50%" valign="top" style="padding:16px 18px;">
                                        <p style="margin:0 0 4px 0; font-size:11px; letter-spacing:0.8px; text-transform:uppercase; color:#0e5c5f; font-weight:700;">Total pagado Bs</p>
                                        <p style="margin:0; font-size:22px; line-height:1.2; color:#0a4648; font-weight:700;">{{ $amountPaidVesLabel }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 12px 28px;">
                            <p style="margin:0 0 10px 0; font-size:13px; font-weight:700; color:#0e5c5f; letter-spacing:0.4px; text-transform:uppercase;">Datos del pago</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #d7e6e6; border-radius:12px; overflow:hidden;">
                                @foreach ([
                                    ['Proveedor', $supplierName],
                                    ['RIF', $supplierTaxId],
                                    ['Factura', $invoiceNumber],
                                    ['Nº control', $controlNumber],
                                    ['Orden de compra', $purchaseNumber],
                                    ['Sucursal', $branchName],
                                    ['Método', $paymentMethodLabel],
                                    ['Forma de pago', $paymentFormLabel],
                                    ['Referencia', $paymentReference],
                                    ['Fecha y hora', $paidAtLabel],
                                ] as $index => $row)
                                    <tr>
                                        <td width="38%" valign="top" style="padding:11px 14px; background-color:{{ $index % 2 === 0 ? '#f7fbfb' : '#ffffff' }}; font-size:13px; color:#5b6b6b; border-bottom:1px solid #e4efef;">
                                            {{ $row[0] }}
                                        </td>
                                        <td valign="top" style="padding:11px 14px; background-color:{{ $index % 2 === 0 ? '#f7fbfb' : '#ffffff' }}; font-size:13px; color:#1a1a1a; font-weight:700; border-bottom:1px solid #e4efef;">
                                            {{ $row[1] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 24px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fff8e8; border:1px solid #f0d48a; border-radius:12px;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <p style="margin:0 0 4px 0; font-size:14px; font-weight:700; color:#8a5a00;">PDF adjunto</p>
                                        <p style="margin:0; font-size:13px; line-height:1.5; color:#6b5420;">
                                            El archivo <strong>{{ $pdfFilename }}</strong> incluye montos, retención SENIAT y forma de pago.
                                            @if ($hasPaymentProof)
                                                También quedó un comprobante asociado a esta cuenta por pagar.
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#0e5c5f; padding:18px 28px;">
                            <p style="margin:0 0 4px 0; color:#ffffff; font-size:13px; font-weight:700;">Farmadoc</p>
                            <p style="margin:0; color:#c5e7e8; font-size:12px; line-height:1.45;">
                                Este correo se generó de forma automática al registrar el pago. Conserve el PDF como comprobante.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
