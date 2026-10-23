<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Manifiesto PWA del portal del empleado, con su propio alcance (`/portal`) para no
 * mezclarse con la app de la tienda (`/app`).
 */
final class EmployeePortalManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $icon = asset('images/logos/favicon.png');

        return response()->json([
            'id' => '/portal',
            'name' => 'Portal del empleado · Farmadoc',
            'short_name' => 'Portal',
            'description' => 'Recibos, constancias, vacaciones, permisos y reposos.',
            'start_url' => route('employee-portal.home', absolute: false),
            'scope' => '/portal',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#eef8fb',
            'theme_color' => '#eef8fb',
            'lang' => 'es-VE',
            'dir' => 'ltr',
            'icons' => [
                ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                [
                    'name' => 'Mis solicitudes',
                    'short_name' => 'Solicitudes',
                    'url' => route('employee-portal.requests', absolute: false),
                    'icons' => [['src' => $icon, 'sizes' => '512x512']],
                ],
            ],
        ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/manifest+json');
    }
}
