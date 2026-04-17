import polyline from '@mapbox/polyline';

export type MapCoordinate = { latitude: number; longitude: number };

export type DrivingRouteResult = {
  coordinates: MapCoordinate[];
  distanceMeters: number;
  durationSeconds: number;
};

export type GeocodeGoogleResult = {
  lat: number;
  lng: number;
  formatted_address: string;
};

/**
 * Geocodifica una dirección con la API de Google (misma clave que Directions).
 * Útil cuando el backend tiene texto de entrega pero falló la geocodificación en servidor.
 */
export async function geocodeGoogleAddress(
  address: string,
  apiKey: string,
): Promise<GeocodeGoogleResult | null> {
  const key = apiKey.trim();
  const addr = address.trim();
  if (key === '' || addr === '') {
    return null;
  }

  const params = new URLSearchParams({
    address: addr,
    key,
    region: 've',
  });

  const res = await fetch(
    `https://maps.googleapis.com/maps/api/geocode/json?${params.toString()}`,
  );
  const data: unknown = await res.json().catch(() => ({}));
  if (!data || typeof data !== 'object') {
    return null;
  }
  const o = data as Record<string, unknown>;
  if (o.status !== 'OK') {
    return null;
  }
  const results = o.results;
  if (!Array.isArray(results) || results.length === 0) {
    return null;
  }
  const first = results[0];
  if (!first || typeof first !== 'object') {
    return null;
  }
  const rec = first as Record<string, unknown>;
  const geometry = rec.geometry;
  if (!geometry || typeof geometry !== 'object') {
    return null;
  }
  const loc = (geometry as Record<string, unknown>).location;
  if (!loc || typeof loc !== 'object') {
    return null;
  }
  const l = loc as Record<string, unknown>;
  const lat = Number(l.lat);
  const lng = Number(l.lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null;
  }
  const formatted =
    typeof rec.formatted_address === 'string' ? rec.formatted_address : addr;

  return { lat, lng, formatted_address: formatted };
}

/**
 * Distancia en línea recta (fallback cuando no hay Directions).
 */
export function haversineDistanceMeters(
  a: { lat: number; lng: number },
  b: { lat: number; lng: number },
): number {
  const R = 6371000;
  const toRad = (d: number): number => (d * Math.PI) / 180;
  const dLat = toRad(b.lat - a.lat);
  const dLon = toRad(b.lng - a.lng);
  const lat1 = toRad(a.lat);
  const lat2 = toRad(b.lat);
  const x =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;

  return 2 * R * Math.asin(Math.sqrt(x));
}

/**
 * Estima duración en coche a partir de metros (sin Directions).
 */
export function estimateDrivingDurationSeconds(distanceMeters: number): number {
  const km = distanceMeters / 1000;
  const hours = km / 32;
  return Math.max(60, Math.round(hours * 3600));
}

/**
 * Obtiene polyline + distancia/duración vía Directions API de Google.
 */
export async function fetchDrivingRouteWithMetadata(
  origin: { lat: number; lng: number },
  destination: { lat: number; lng: number },
  apiKey: string,
): Promise<DrivingRouteResult | null> {
  const key = apiKey.trim();
  if (key === '') {
    return null;
  }

  const params = new URLSearchParams({
    origin: `${origin.lat},${origin.lng}`,
    destination: `${destination.lat},${destination.lng}`,
    mode: 'driving',
    key,
  });

  const res = await fetch(
    `https://maps.googleapis.com/maps/api/directions/json?${params.toString()}`,
  );
  const data: unknown = await res.json().catch(() => ({}));
  if (!data || typeof data !== 'object') {
    return null;
  }
  const o = data as Record<string, unknown>;
  if (o.status !== 'OK') {
    return null;
  }
  const routes = o.routes;
  if (!Array.isArray(routes) || routes.length === 0) {
    return null;
  }
  const first = routes[0];
  if (!first || typeof first !== 'object') {
    return null;
  }
  const rec = first as Record<string, unknown>;
  const overview = rec.overview_polyline;
  if (!overview || typeof overview !== 'object') {
    return null;
  }
  const points = (overview as Record<string, unknown>).points;
  if (typeof points !== 'string' || points === '') {
    return null;
  }

  const legs = rec.legs;
  let distanceMeters = 0;
  let durationSeconds = 0;
  if (Array.isArray(legs)) {
    for (const leg of legs) {
      if (!leg || typeof leg !== 'object') {
        continue;
      }
      const l = leg as Record<string, unknown>;
      const dist = l.distance;
      const dur = l.duration;
      if (dist && typeof dist === 'object') {
        const v = (dist as Record<string, unknown>).value;
        if (typeof v === 'number') {
          distanceMeters += v;
        }
      }
      if (dur && typeof dur === 'object') {
        const v = (dur as Record<string, unknown>).value;
        if (typeof v === 'number') {
          durationSeconds += v;
        }
      }
    }
  }

  const pairs = polyline.decode(points, 5);
  const coordinates = pairs.map(([latitude, longitude]) => ({
    latitude,
    longitude,
  }));

  return {
    coordinates,
    distanceMeters: distanceMeters > 0 ? distanceMeters : 0,
    durationSeconds: durationSeconds > 0 ? durationSeconds : 0,
  };
}

/**
 * Obtiene la ruta en carro (overview polyline) vía Directions API de Google.
 * La clave debe tener Directions API habilitada y restricciones por app (iOS bundle / Android package).
 */
export async function fetchDrivingRouteCoordinates(
  origin: { lat: number; lng: number },
  destination: { lat: number; lng: number },
  apiKey: string,
): Promise<MapCoordinate[] | null> {
  const meta = await fetchDrivingRouteWithMetadata(origin, destination, apiKey);

  return meta?.coordinates ?? null;
}

export function straightLineRoute(
  origin: MapCoordinate,
  destination: MapCoordinate,
): MapCoordinate[] {
  return [origin, destination];
}

/**
 * Filtra puntos válidos para MapView / Polyline.
 */
export function sanitizeRouteCoords(coords: MapCoordinate[]): MapCoordinate[] {
  return coords.filter(
    (c) =>
      Number.isFinite(c.latitude) &&
      Number.isFinite(c.longitude) &&
      Math.abs(c.latitude) <= 90 &&
      Math.abs(c.longitude) <= 180,
  );
}

/**
 * Decodifica `overview_polyline` de Directions (servidor o cliente).
 */
export function decodeEncodedPolyline(encoded: string): MapCoordinate[] {
  try {
    const trimmed = encoded.trim();
    if (trimmed === '') {
      return [];
    }
    const pairs = polyline.decode(trimmed, 5);
    return sanitizeRouteCoords(
      pairs.map(([latitude, longitude]) => ({ latitude, longitude })),
    );
  } catch {
    return [];
  }
}
