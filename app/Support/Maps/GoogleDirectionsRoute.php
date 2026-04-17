<?php

namespace App\Support\Maps;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GoogleDirectionsRoute
{
    /**
     * Ruta en coche entre dos puntos (Directions API, misma clave de servidor que geocodificación).
     *
     * @return array{encoded_polyline: string, distance_meters: int, duration_seconds: int}|null
     */
    public static function drivingRoute(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
    ): ?array {
        $key = config('services.google.maps_server_api_key');
        if (! filled($key)) {
            return null;
        }

        $response = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/directions/json', [
            'origin' => $originLat.','.$originLng,
            'destination' => $destLat.','.$destLng,
            'mode' => 'driving',
            'key' => $key,
        ]);

        if (! $response->successful()) {
            if (config('app.debug')) {
                Log::debug('google_directions_http_failed', [
                    'status' => $response->status(),
                ]);
            }

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        if (($data['status'] ?? '') !== 'OK' || empty($data['routes'][0])) {
            if (config('app.debug')) {
                Log::debug('google_directions_non_ok', [
                    'status' => $data['status'] ?? null,
                    'error_message' => $data['error_message'] ?? null,
                ]);
            }

            return null;
        }

        /** @var array<string, mixed> $route */
        $route = $data['routes'][0];
        $overview = $route['overview_polyline'] ?? null;
        if (! is_array($overview)) {
            return null;
        }
        $points = $overview['points'] ?? null;
        if (! is_string($points) || $points === '') {
            return null;
        }

        $distanceMeters = 0;
        $durationSeconds = 0;
        $legs = $route['legs'] ?? [];
        if (is_array($legs)) {
            foreach ($legs as $leg) {
                if (! is_array($leg)) {
                    continue;
                }
                $distanceMeters += (int) data_get($leg, 'distance.value', 0);
                $durationSeconds += (int) data_get($leg, 'duration.value', 0);
            }
        }

        return [
            'encoded_polyline' => $points,
            'distance_meters' => $distanceMeters,
            'duration_seconds' => $durationSeconds,
        ];
    }
}
