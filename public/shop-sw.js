/**
 * Service worker de la app Farmadoc (`/app`).
 *
 * Estrategia deliberadamente conservadora: la tienda muestra precios y existencias
 * reales, así que la red siempre manda. El caché solo cubre estáticos y una pantalla
 * de respaldo para cuando no hay conexión.
 */

const VERSION = 'farmadoc-shop-v8';
const STATIC_CACHE = `${VERSION}-static`;
const OFFLINE_URL = '/app/offline';

const PRECACHE = [
    OFFLINE_URL,
    '/images/logos/favicon.png',
    '/images/logos/farmadoc-ligth.png',
    '/images/logos/farmadoc-dark.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .catch(() => undefined)
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('farmadoc-shop-') && ! key.startsWith(VERSION))
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // OAuth (Google): el 302 sale del origen. Si el SW lo intercepta y sigue
    // el redirect, el fetch cruzado falla y la PWA muestra «Sin conexión».
    if (url.pathname.startsWith('/app/auth/')) {
        return;
    }

    // Navegación: red primero, respaldo offline si falla.
    // Chrome rechaza devolver un Response.redirected en un FetchEvent de navigate
    // (p. ej. /app → /app/bienvenida). Se sigue el redirect y se limpia el flag.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(new Request(request, { redirect: 'follow' }))
                .then((response) => {
                    if (! response.redirected) {
                        return response;
                    }

                    return new Response(response.body, {
                        status: response.status,
                        statusText: response.statusText,
                        headers: response.headers,
                    });
                })
                .catch(() => caches.match(OFFLINE_URL).then(
                    (cached) => cached ?? new Response(
                        '<!doctype html><meta charset="utf-8"><title>Sin conexión</title>'
                        + '<body style="font-family:system-ui;display:grid;place-items:center;height:100vh;margin:0;'
                        + 'background:#f2f9f9;color:#10282c;text-align:center;padding:2rem">'
                        + '<div><h1 style="font-size:1.3rem">Sin conexión</h1>'
                        + '<p style="color:#5b6f73">Revisa tu internet y vuelve a intentarlo.</p></div>',
                        { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 },
                    ),
                )),
        );

        return;
    }

    // Estáticos versionados por Vite e imágenes: caché primero.
    const isStatic = url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/images/')
        || url.pathname.startsWith('/storage/')
        || url.pathname.startsWith('/fonts/');

    if (! isStatic) {
        return;
    }

    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok && response.type === 'basic') {
                    const copy = response.clone();
                    caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            })
            .catch(() => caches.match(request)),
    );
});
