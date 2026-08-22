<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Manifiesto PWA de la tienda `/app`, separado del `site.webmanifest` del sitio público
 * para que la app instalada arranque en `/app` y no en la portada.
 */
class ShopManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $icon = asset('images/logos/favicon.png');

        return response()->json([
            'id' => '/app',
            'name' => 'Farmadoc',
            'short_name' => 'Farmadoc',
            'description' => 'Tu farmacia en el bolsillo: medicinas, cuidado personal y entrega a domicilio.',
            'start_url' => route('shop.home', absolute: false),
            'scope' => route('shop.home', absolute: false),
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'portrait',
            'background_color' => '#F2F9F9',
            'theme_color' => '#F2F9F9',
            'lang' => 'es-VE',
            'dir' => 'ltr',
            'categories' => ['shopping', 'health', 'medical'],
            'icons' => [
                ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => $icon, 'sizes' => '1024x1024', 'type' => 'image/png', 'purpose' => 'any'],
            ],
            'shortcuts' => [
                [
                    'name' => 'Buscar medicinas',
                    'short_name' => 'Buscar',
                    'url' => route('shop.search', absolute: false),
                    'icons' => [['src' => $icon, 'sizes' => '512x512']],
                ],
                [
                    'name' => 'Mi carrito',
                    'short_name' => 'Carrito',
                    'url' => route('shop.cart', absolute: false),
                    'icons' => [['src' => $icon, 'sizes' => '512x512']],
                ],
            ],
        ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/manifest+json');
    }
}
