/**
 * Service worker del portal del empleado (`/portal`).
 *
 * Solo existe para que el portal sea instalable y pueda mostrar el número de avisos en el ícono.
 * No intercepta peticiones: todo (incluido Livewire) va directo a la red, sin caché.
 */

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});
