<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class NominatimProxyController extends Controller
{
    /**
     * Proxy de búsqueda: intenta Nominatim y, si falla o no hay resultados, usa Photon (Komoot).
     * Nominatim suele responder 429 por límite de uso; el cliente no debe recibir 502 por eso.
     */
    public function search(Request $request): JsonResponse
    {
        $q = Str::limit(trim((string) $request->query('q', '')), 300, '');
        if ($q === '') {
            return response()->json([]);
        }

        $fromNominatim = $this->fetchNominatimSearch($q);
        if ($fromNominatim !== [] && $this->isNonEmptyNominatimSearchList($fromNominatim)) {
            return response()->json($fromNominatim);
        }

        return response()->json($this->fetchPhotonSearch($q));
    }

    /**
     * Geocodificación inversa: Nominatim primero; si falla, Photon.
     */
    public function reverse(Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json([]);
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;

        $fromNominatim = $this->fetchNominatimReverse($latF, $lngF);
        if (is_array($fromNominatim) && isset($fromNominatim['address'])) {
            return response()->json($fromNominatim);
        }

        $fromPhoton = $this->fetchPhotonReverse($latF, $lngF);

        return response()->json($fromPhoton ?? []);
    }

    /**
     * @return array<int, mixed>
     */
    private function fetchNominatimSearch(string $q): array
    {
        try {
            $response = $this->nominatimClient()->get('https://nominatim.openstreetmap.org/search', [
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 5,
                'q' => $q,
            ]);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @return array<string, mixed>|array{}
     */
    private function fetchNominatimReverse(float $lat, float $lng): array
    {
        try {
            $response = $this->nominatimClient()->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'json',
                'addressdetails' => 1,
                'lat' => $lat,
                'lon' => $lng,
            ]);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @return array<int, mixed>
     */
    private function fetchPhotonSearch(string $q): array
    {
        $base = rtrim((string) config('services.photon.url', 'https://photon.komoot.io'), '/');

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->get($base.'/api', [
                    'q' => $q,
                    'limit' => 5,
                    'lang' => 'es',
                ]);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        /** @var array<string, mixed>|null $json */
        $json = $response->json();

        return $this->photonFeaturesToNominatimSearchResults(is_array($json) ? $json : []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPhotonReverse(float $lat, float $lng): ?array
    {
        $base = rtrim((string) config('services.photon.url', 'https://photon.komoot.io'), '/');

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->get($base.'/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'lang' => 'es',
                ]);
        } catch (Throwable) {
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
        if (! is_array($features) || $features === []) {
            return null;
        }

        $first = $features[0];

        return is_array($first) ? $this->photonFeatureToNominatimReverseItem($first) : null;
    }

    /**
     * @param  array<int, mixed>  $list
     */
    private function isNonEmptyNominatimSearchList(array $list): bool
    {
        if ($list === [] || ! isset($list[0]) || ! is_array($list[0])) {
            return false;
        }

        $first = $list[0];

        return isset($first['lat'], $first['lon']);
    }

    /**
     * @param  array<string, mixed>  $geoJson
     * @return array<int, array<string, mixed>>
     */
    private function photonFeaturesToNominatimSearchResults(array $geoJson): array
    {
        $features = $geoJson['features'] ?? [];
        if (! is_array($features)) {
            return [];
        }

        $out = [];
        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $mapped = $this->photonFeatureToNominatimSearchItem($feature);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>|null
     */
    private function photonFeatureToNominatimSearchItem(array $feature): ?array
    {
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
        $p = $feature['properties'] ?? [];
        $p = is_array($p) ? $p : [];

        $address = $this->photonPropertiesToNominatimAddress($p);
        $displayName = $this->photonPropertiesToDisplayName($p);

        return [
            'lat' => (string) $lat,
            'lon' => (string) $lng,
            'display_name' => $displayName,
            'address' => $address,
        ];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>|null
     */
    private function photonFeatureToNominatimReverseItem(array $feature): ?array
    {
        $mapped = $this->photonFeatureToNominatimSearchItem($feature);
        if ($mapped === null) {
            return null;
        }

        return [
            'lat' => $mapped['lat'],
            'lon' => $mapped['lon'],
            'display_name' => $mapped['display_name'],
            'address' => $mapped['address'],
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, string|null>
     */
    private function photonPropertiesToNominatimAddress(array $p): array
    {
        $city = $p['city'] ?? $p['town'] ?? $p['village'] ?? null;
        if ($city === null && isset($p['name'])) {
            $type = (string) ($p['type'] ?? '');
            if (in_array($type, ['city', 'town', 'village', 'district', 'county'], true)) {
                $city = (string) $p['name'];
            }
        }

        return [
            'house_number' => isset($p['housenumber']) ? (string) $p['housenumber'] : null,
            'road' => isset($p['street']) ? (string) $p['street'] : null,
            'neighbourhood' => isset($p['district']) ? (string) $p['district'] : null,
            'city' => $city !== null ? (string) $city : null,
            'state' => isset($p['state']) ? (string) $p['state'] : null,
            'country' => isset($p['country']) ? (string) $p['country'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function photonPropertiesToDisplayName(array $p): string
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

        $line = implode(', ', array_map(static fn (mixed $v): string => (string) $v, $parts));

        return Str::limit($line, 500, '');
    }

    private function nominatimUserAgent(): string
    {
        $url = (string) config('app.url', 'https://example.com');
        $email = config('services.nominatim.contact_email');
        if (filled($email)) {
            return 'Farmasysdoc/1.0 ('.$url.'; contact: '.$email.')';
        }

        return 'Farmasysdoc/1.0 (geocoding proxy; '.$url.')';
    }

    private function nominatimClient(): PendingRequest
    {
        return Http::timeout(12)
            ->withHeaders([
                'User-Agent' => $this->nominatimUserAgent(),
                'Accept' => 'application/json',
                'Accept-Language' => 'es,en;q=0.8',
                'Referer' => (string) config('app.url', ''),
            ]);
    }
}
