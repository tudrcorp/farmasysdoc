<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/docs/api', 'public.api-docs')->name('public.api-docs');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::get('/pp', function () {
    /**
     * POST /api/external/service-orders
     * Body: partner_company (code), paciente, diagnosis, items[name+indicacion]
     */
    $baseUrl = 'https://farmasysdoc.test';
    $token = 'fd_ba51587cdaee4df907ef3a5441206484b5573378ee0f6fbdb7f98256e2c0f52f';

    $payload = [
        'partner_company' => 'ALDO-2026-001',
        'status' => 'en-proceso',
        'priority' => 'media',
        'service_type' => 'consulta',
        'external_reference' => 'EXT-REF-001',
        'patient_name' => 'Maria Gomez',
        'patient_document' => '1234567890',
        'patient_phone' => '3001234567',
        'patient_email' => 'maria@example.com',
        'diagnosis' => 'Control',
        'items' => [
            [
                'name' => 'Paracetamol 500 mg',
                'indicacion' => '1 tableta cada 10 horas',
            ],
            [
                'name' => 'Ibuprofeno 400 mg',
                'indicacion' => '2 tabletas cada 8 horas',
            ],
        ],
    ];

    $url = rtrim($baseUrl, '/').'/api/external/service-orders';
    // dd($url);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer '.$token,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    echo 'HTTP '.$status.PHP_EOL;
    echo $body.PHP_EOL;
})->name('pp');

Route::get('/bcv', function () {
    // $response = Http::timeout(5)->get('https://ve.dolarapi.com/v1/dolares');
    // dd($response->json());
    try {
        $response = Http::timeout(config('dolar.timeout', 8))
            ->acceptJson()
            ->get(rtrim((string) config('dolar.base_url'), '/').'/v1/estado');

        if (! $response->successful()) {
            return false;
        }

        $estado = $response->json('estado');

        return is_string($estado) && strcasecmp(trim($estado), 'Disponible') !== 0;
    } catch (Throwable) {
        return false;
    }
})->name('bcv');

require __DIR__.'/settings.php';
