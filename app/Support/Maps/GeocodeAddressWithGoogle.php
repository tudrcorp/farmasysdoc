<?php

declare(strict_types=1);

namespace App\Support\Maps;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class GeocodeAddressWithGoogle
{
    /**
     * Geocodifica la dirección: intenta Google (clave de servidor) y, si falla o no hay clave, Photon (Komoot).
     *
     * @return array{lat: float, lng: float, formatted_address: string}|null
     */
    public static function firstResult(string $address): ?array
    {
        $trimmed = trim($address);
        if ($trimmed === '') {
            return null;
        }

        $key = config('services.google.maps_server_api_key');
        if (is_string($key) && $key !== '') {
            $fromGoogle = self::firstResultFromGoogle($trimmed, $key);
            if ($fromGoogle !== null) {
                return $fromGoogle;
            }
        }

        $fromPhoton = self::firstResultFromPhoton($trimmed);
        if ($fromPhoton === null) {
            Log::notice('Geocoding sin resultado (Google y Photon)', ['address_sample' => Str::limit($trimmed, 80, '')]);
        }

        return $fromPhoton;
    }

    /**
     * @return array{lat: float, lng: float, formatted_address: string}|null
     */
    private static function firstResultFromGoogle(string $trimmed, string $key): ?array
    {
        $response = Http::timeout(12)
            ->acceptJson()
            ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $trimmed,
                'key' => $key,
            ]);

        if (! $response->successful()) {
            Log::debug('Google Geocoding HTTP error', ['status' => $response->status()]);

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0])) {
            Log::debug('Google Geocoding no result', ['status' => $data['status'] ?? 'unknown']);

            return null;
        }

        /** @var array<string, mixed> $result */
        $result = $data['results'][0];
        $loc = $result['geometry']['location'] ?? null;
        if (! is_array($loc) || ! isset($loc['lat'], $loc['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $loc['lat'],
            'lng' => (float) $loc['lng'],
            'formatted_address' => (string) ($result['formatted_address'] ?? $trimmed),
        ];
    }

    /**
     * @return array{lat: float, lng: float, formatted_address: string}|null
     */
    private static function firstResultFromPhoton(string $trimmed): ?array
    {
        $base = rtrim((string) config('services.photon.url', 'https://photon.komoot.io'), '/');

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->get($base.'/api', [
                    'q' => $trimmed,
                    'limit' => 1,
                    'lang' => 'es',
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed>|null $json */
        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        $features = $json['features'] ?? [];
        if (! is_array($features) || $features === [] || ! is_array($features[0])) {
            return null;
        }

        /** @var array<string, mixed> $feature */
        $feature = $features[0];
        $geometry = $feature['geometry'] ?? [];
        if (! is_array($geometry) || ($geometry['type'] ?? '') !== 'Point') {
            return null;
        }

        $coords = $geometry['coordinates'] ?? [];
        if (! is_array($coords) || count($coords) < 2) {
            return null;
        }

        $lng = (float) $coords[0];
        $lat = (float) $coords[1];
        $props = $feature['properties'] ?? [];
        $props = is_array($props) ? $props : [];
        $label = self::photonPropertiesToLabel($props);

        return [
            'lat' => $lat,
            'lng' => $lng,
            'formatted_address' => $label !== '' ? $label : $trimmed,
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private static function photonPropertiesToLabel(array $p): string
    {
        $parts = array_filter([
            $p['housenumber'] ?? null,
            $p['street'] ?? null,
            $p['name'] ?? null,
            $p['district'] ?? null,
            $p['city'] ?? $p['town'] ?? $p['village'] ?? null,
            $p['state'] ?? null,
            $p['country'] ?? null,
        ], fn (mixed $v): bool => filled($v));

        return implode(', ', array_map(static fn (mixed $v): string => (string) $v, $parts));
    }
}
