<?php

namespace App\Support\Fiscal;

use App\Models\FiscalPrinter;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Instrucciones de instalación del agente Windows que se muestran en Farmaadmin.
 */
final class FiscalAgentInstallGuide
{
    public static function installCommand(?FiscalPrinter $printer = null): string
    {
        $port = filled($printer?->connection_port) ? (string) $printer->connection_port : 'COM5';

        return 'powershell -ExecutionPolicy Bypass -File .\\install.ps1 -ServerUrl '.rtrim((string) config('app.url'), '/')
            .' -Token PEGAR_TOKEN_fd_fp_ -ComPort '.$port;
    }

    public static function html(?FiscalPrinter $printer = null): HtmlString
    {
        $package = FiscalAgentPackage::current();
        $command = e(self::installCommand($printer));

        $packageInfo = $package === null
            ? '<p style="color:#b91c1c"><strong>Aún no hay paquete subido.</strong> Un administrador debe usar «Subir versión del agente».</p>'
            : '<p>Versión disponible: <strong>'.e($package['version']).'</strong> · '.number_format($package['size'] / 1024, 0, ',', '.').' KB'
                .' · subida el '.e(Carbon::parse($package['uploaded_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i'))
                .' por '.e($package['uploaded_by']).'<br><span style="font-size:.75rem;color:#71717a">SHA-256: '.e($package['sha256']).'</span></p>';

        $steps = [
            'En Farmaadmin, cree la máquina fiscal (o use «Regenerar token del agente») y <strong>copie el token</strong>; solo se muestra una vez.',
            'En la PC de la caja, descargue el .zip desde este botón y descomprímalo, p. ej. en <code>C:\\Instaladores\\FarmadocFiscalAgent</code>.',
            '<strong>Cierre el sistema de facturación actual (Valery)</strong> solo durante la instalación: el diagnóstico lee la máquina fiscal una vez.',
            'Abra PowerShell <strong>como Administrador</strong> en esa carpeta y ejecute:<br><code>Get-ChildItem | Unblock-File</code>',
            'Luego (reemplace el token):<br><code style="user-select:all">'.$command.'</code>',
            'El instalador hace un diagnóstico y <strong>solo inicia el servicio si todo está OK</strong>. Ya puede volver a abrir Valery.',
            'En Farmaadmin, cargue los <strong>medios de pago</strong> de esa máquina y pásela a <strong>Simulación</strong>. Cuando la lista de chequeo esté completa, pásela a <strong>Activa</strong>.',
            'Para actualizar el agente más adelante: descargue la nueva versión y ejecute <code>.\\install.ps1</code> sin parámetros (conserva la configuración). Para corregir la URL, el token o el puerto, vuelva a ejecutarlo con <code>-ServerUrl</code>, <code>-Token</code> o <code>-ComPort</code>: sobrescriben solo esos valores.',
        ];

        $list = collect($steps)
            ->map(fn (string $step): string => '<li style="margin:.35rem 0">'.$step.'</li>')
            ->implode('');

        return new HtmlString(
            '<div style="font-size:.875rem;line-height:1.5">'
            .$packageInfo
            .'<ol style="margin:.75rem 0 0;padding-left:1.25rem;list-style:decimal">'.$list.'</ol>'
            .'<p style="margin-top:.75rem;color:#71717a">Diagnóstico en cualquier momento: <code>"C:\\Program Files (x86)\\Farmadoc\\FiscalAgent\\FarmadocFiscalAgent.exe" --check</code> · Logs: <code>C:\\ProgramData\\FarmadocFiscalAgent\\logs</code></p>'
            .'</div>'
        );
    }
}
