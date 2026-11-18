@php
    $teal = '#0BA2AF';
    $tealDark = '#08808B';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isInvitation ? 'Su acceso a dale · Aliados' : 'Cambie su clave' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 24px 12px; background: #EEF2F5; font-family: Poppins, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1F2A33; line-height: 1.55;">
    <div style="max-width: 540px; margin: 0 auto;">
        <div style="background: #ffffff; border-radius: 18px; overflow: hidden; border: 1px solid #DCE3E8;">
            <div style="padding: 28px 28px 8px; text-align: center;">
                <img src="{{ asset('images/dale/logo-dale.png') }}" alt="dale" width="132" style="width: 132px; max-width: 132px; height: auto; display: inline-block;">
                <p style="margin: 6px 0 0; font-size: 12px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: {{ $teal }};">Aliados</p>
            </div>
            <div style="padding: 20px 28px 28px;">
                <h1 style="margin: 0 0 10px; font-size: 21px; font-weight: 700; color: #102027;">
                    {{ $isInvitation ? 'Le damos la bienvenida, '.$recipientName : 'Hola, '.$recipientName }}
                </h1>
                @if ($isInvitation)
                    <p style="margin: 0 0 18px; font-size: 15px; color: #4A5A66;">
                        @if (filled($companyName))
                            <strong style="color: #102027;">{{ $companyName }}</strong> le dio acceso a <strong style="color: #102027;">dale · Aliados</strong>,
                        @else
                            Tiene acceso a <strong style="color: #102027;">dale · Aliados</strong>,
                        @endif
                        donde verá los planes, convenios, pedidos y el estado de cuenta de su empresa. Para empezar, cree su clave:
                    </p>
                @else
                    <p style="margin: 0 0 18px; font-size: 15px; color: #4A5A66;">Recibimos una solicitud para cambiar la clave de su cuenta. Use este botón para crear una nueva:</p>
                @endif

                <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto 20px;">
                    <tr>
                        <td style="border-radius: 12px; background: {{ $tealDark }};">
                            <a href="{{ $actionUrl }}" style="display: inline-block; padding: 14px 28px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 12px;">
                                {{ $isInvitation ? 'Crear mi clave' : 'Cambiar mi clave' }}
                            </a>
                        </td>
                    </tr>
                </table>

                <div style="background: #F4F7F9; border-radius: 12px; padding: 14px 16px; font-size: 14px; color: #4A5A66;">
                    <p style="margin: 0;">Usuario: <strong style="color: #102027;">{{ $email }}</strong></p>
                    <p style="margin: 6px 0 0;">El enlace es personal, de un solo uso y vence en {{ $expiresInHours }} horas.</p>
                </div>

                <p style="margin: 18px 0 0; font-size: 12px; color: #7A8A96;">Si el botón no abre, copie este enlace en su navegador:<br><span style="word-break: break-all; color: {{ $tealDark }};">{{ $actionUrl }}</span></p>
                <p style="margin: 12px 0 0; font-size: 12px; color: #7A8A96;">
                    {{ $isInvitation ? 'Si no esperaba este correo, puede ignorarlo.' : 'Si usted no lo pidió, ignore este correo: su clave actual sigue funcionando.' }}
                </p>
            </div>
        </div>
        <p style="margin: 16px 0 0; text-align: center; font-size: 11px; color: #8796A1;">dale · Aliados — operado por Farmadoc</p>
    </div>
</body>
</html>
