<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/docs/api', 'public.api-docs')->name('public.api-docs');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::get('/pp', function () {
    /**
     * GET /api/external/status — sin token.
     */

    $baseUrl = "https://farmasysdoc.test";

    $url = rtrim($baseUrl, '/') . '/api/external/status';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        fwrite(STDERR, 'cURL error: ' . curl_strerror($errno) . PHP_EOL);
        exit(1);
    }

    return json_decode($body);
})->name('pp');

require __DIR__ . '/settings.php';
