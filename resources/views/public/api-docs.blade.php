<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Documentacion publica para aliados de la API de FarmaSysDoc.">
    <title>Documentacion API Aliados - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900 antialiased transition-colors duration-300 dark:bg-zinc-950 dark:text-zinc-100">
    <div class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute -left-24 top-20 h-72 w-72 rounded-full bg-cyan-200/40 blur-3xl dark:bg-cyan-500/20"></div>
        <div class="absolute right-0 top-0 h-96 w-96 rounded-full bg-blue-200/40 blur-3xl dark:bg-blue-500/10"></div>
    </div>

    <header class="sticky top-0 z-30 border-b border-zinc-200/70 bg-white/75 backdrop-blur-xl dark:border-white/10 dark:bg-zinc-900/70">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                <img src="{{ asset('images/logos/farmadoc-ligth.png') }}" alt="FarmaDoc" class="h-11 w-auto sm:h-12 dark:hidden">
                <img src="{{ asset('images/logos/farmadoc-dark.png') }}" alt="FarmaDoc" class="hidden h-11 w-auto sm:h-12 dark:block">
                <span class="text-sm font-semibold sm:text-base">API para Aliados</span>
            </a>

            <nav class="hidden items-center gap-2 md:flex">
                <a href="#auth" class="rounded-xl px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-900/5 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white">Autenticacion</a>
                <a href="#api-status" class="rounded-xl px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-900/5 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white">Estado API</a>
                <a href="#endpoints" class="rounded-xl px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-900/5 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white">Endpoints</a>
                <a href="#inventory" class="rounded-xl px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-900/5 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white">Inventario</a>
                <a href="#external-branches" class="rounded-xl px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-900/5 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white">Sucursales</a>
                <a href="#inventory-by-branch" class="rounded-xl px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-900/5 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white">Inventario sucursal</a>
                <a href="#external-orders" class="rounded-xl px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-900/5 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white">Pedidos</a>
                <a href="#service-orders" class="rounded-xl px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-900/5 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white">Orden servicio</a>
                <a href="#playground" class="rounded-xl px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-900/5 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white">Playground</a>
            </nav>

            <button id="theme-toggle" type="button" class="inline-flex items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                <span id="theme-label">Modo oscuro</span>
            </button>
        </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-3xl border border-zinc-200/80 bg-white/80 p-6 shadow-xl shadow-zinc-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-white/5 sm:p-8">
            <div class="grid gap-8 lg:grid-cols-2">
                <div class="space-y-5">
                    <p class="inline-flex items-center gap-2 rounded-full bg-cyan-100 px-3 py-1 text-xs font-semibold text-cyan-800 dark:bg-cyan-500/15 dark:text-cyan-200">
                        API publica para aliados
                    </p>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Integra pedidos e inventario en minutos</h1>
                    <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-300 sm:text-base">
                        Esta documentacion esta pensada para ser clara y practica. Puedes consumir la API con cualquier lenguaje de programacion porque todo funciona con HTTP y JSON.
                    </p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-zinc-200/80 bg-zinc-50/90 p-4 dark:border-white/10 dark:bg-zinc-900/60">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Base URL</p>
                            <p class="mt-1 font-mono text-xs">/api/external</p>
                        </div>
                        <div class="rounded-2xl border border-zinc-200/80 bg-zinc-50/90 p-4 dark:border-white/10 dark:bg-zinc-900/60">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Formato</p>
                            <p class="mt-1 text-xs font-semibold">JSON UTF-8</p>
                        </div>
                        <div class="rounded-2xl border border-zinc-200/80 bg-zinc-50/90 p-4 dark:border-white/10 dark:bg-zinc-900/60">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Auth</p>
                            <p class="mt-1 text-xs font-semibold">Bearer Token</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-zinc-200/80 bg-zinc-50/80 p-5 dark:border-white/10 dark:bg-zinc-900/70">
                    <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Flujo recomendado</h2>
                    <ol class="mt-4 space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
                        <li class="flex gap-3"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xs font-bold text-white dark:bg-white dark:text-zinc-900">1</span><span>Opcional: consulta <code class="rounded bg-zinc-200/70 px-1 dark:bg-zinc-700">GET /api/external/status</code> para verificar que la API está <strong>activa</strong> sin token (reduce carga si el servicio no está disponible).</span></li>
                        <li class="flex gap-3"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xs font-bold text-white dark:bg-white dark:text-zinc-900">2</span><span>Crea tu cliente API en el panel y copia el secreto que empieza por <code class="rounded bg-zinc-200/70 px-1 dark:bg-zinc-700">fd_</code> (no la huella truncada).</span></li>
                        <li class="flex gap-3"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xs font-bold text-white dark:bg-white dark:text-zinc-900">3</span><span>Agrega el header <code class="rounded bg-zinc-200/70 px-1 dark:bg-zinc-700">Authorization: Bearer TOKEN</code> en las operaciones con datos.</span></li>
                        <li class="flex gap-3"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xs font-bold text-white dark:bg-white dark:text-zinc-900">4</span><span>Obtén el <strong>código de compañía aliada</strong> en el panel (<code class="rounded bg-zinc-200/70 px-1 dark:bg-zinc-700">partner_companies.code</code>) y envíalo como <code class="rounded bg-zinc-200/70 px-1 dark:bg-zinc-700">partner_company</code> en inventario, listado de sucursales, inventario por sucursal, pedidos y órdenes de servicio.</span></li>
                        <li class="flex gap-3"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xs font-bold text-white dark:bg-white dark:text-zinc-900">5</span><span>Consume los endpoints con JSON o query. Maneja errores 401, 403 y 422.</span></li>
                    </ol>
                </div>
            </div>
        </section>

        <section id="auth" class="rounded-3xl border border-zinc-200/80 bg-white/80 p-6 shadow-xl shadow-zinc-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-white/5 sm:p-8">
            <h2 class="text-2xl font-bold">Autenticacion y reglas</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <article class="rounded-2xl border border-zinc-200/80 bg-zinc-50/80 p-4 dark:border-white/10 dark:bg-zinc-900/70">
                    <h3 class="font-semibold">Header requerido</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Las operaciones con datos (inventario global, inventario por sucursal, sucursales, pedidos, órdenes de servicio) deben enviar token Bearer. La excepción es <code class="rounded bg-zinc-200/70 px-1 dark:bg-zinc-700">GET /api/external/status</code>: no usa token ni <code class="rounded bg-zinc-200/70 px-1 dark:bg-zinc-700">partner_company</code>.</p>
                    <pre class="mt-3 overflow-auto rounded-xl bg-zinc-900 p-3 text-xs text-zinc-100"><code id="auth-header-code">Authorization: Bearer fd_tu_secreto_del_panel</code></pre>
                    <button data-copy-target="auth-header-code" class="copy-btn mt-3 rounded-xl bg-zinc-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">Copiar</button>
                </article>
                <article class="rounded-2xl border border-zinc-200/80 bg-zinc-50/80 p-4 dark:border-white/10 dark:bg-zinc-900/70">
                    <h3 class="font-semibold">Códigos de error frecuentes</h3>
                    <ul class="mt-2 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                        <li><strong>401</strong> - Token ausente o invalido</li>
                        <li><strong>403</strong> - IP no autorizada para el cliente</li>
                        <li><strong>422</strong> - Error de validacion de campos</li>
                        <li><strong>429</strong> - Limite impuesto fuera de la app (p. ej. CDN/WAF); la API de integracion no aplica throttle HTTP en Laravel.</li>
                    </ul>
                </article>
            </div>
            <div class="mt-6 rounded-2xl border border-amber-200/90 bg-amber-50/90 p-5 dark:border-amber-500/30 dark:bg-amber-500/10">
                <h3 class="font-semibold text-amber-950 dark:text-amber-100">Token correcto (evita el 401 «Token inválido o inactivo»)</h3>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-amber-950/90 dark:text-amber-50/90">
                    <li>El valor en <code class="rounded bg-amber-200/80 px-1 font-mono text-xs dark:bg-amber-900/50">Authorization: Bearer …</code> debe ser el <strong>secreto en texto plano</strong> que te entrega el panel al <strong>crear</strong> o <strong>regenerar</strong> el cliente API.</li>
                    <li>Ese secreto <strong>siempre empieza por</strong> <code class="rounded bg-amber-200/80 px-1 font-mono text-xs dark:bg-amber-900/50">fd_</code> y tiene unos <strong>67 caracteres</strong> (prefijo + hex).</li>
                    <li><strong>No</strong> uses la «huella» que ves en la ficha del cliente (texto truncado): es solo referencia del hash guardado en el servidor.</li>
                    <li><strong>No</strong> envíes los <strong>64 caracteres hexadecimales</strong> del hash SHA-256: el servidor aplica de nuevo el hash a lo que envías; si mandas el hash como si fuera el secreto, la autenticación falla.</li>
                    <li>Si perdiste el secreto, pide en el panel <strong>Regenerar token</strong> y distribuye el nuevo valor por un canal seguro.</li>
                </ul>
                <p class="mt-3 text-xs text-amber-900/80 dark:text-amber-200/80">Si el servidor detecta un Bearer de 64 hex (típico error), la respuesta 401 puede incluir el campo <code class="font-mono">hint</code> con esta aclaración.</p>
            </div>
        </section>

        <section id="endpoints" class="space-y-4">
            <div class="rounded-2xl border border-zinc-200/80 bg-zinc-50/90 p-4 text-sm leading-relaxed text-zinc-700 dark:border-white/10 dark:bg-zinc-900/60 dark:text-zinc-200">
                <p><strong>Prefijo común:</strong> <code class="rounded bg-zinc-200/80 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">/api/external</code>. En inventario (global y por sucursal), listado de sucursales, pedidos y órdenes de servicio debes enviar <code class="rounded bg-zinc-200/80 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">partner_company</code> (código en <code class="rounded bg-zinc-200/80 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">partner_companies.code</code>). El inventario por sucursal además requiere <code class="rounded bg-zinc-200/80 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">branch_id</code> (id numérico de una sucursal <strong>activa</strong>). <strong>Excepción:</strong> <code class="rounded bg-zinc-200/80 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">GET /status</code> no lleva token ni <code class="rounded bg-zinc-200/80 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">partner_company</code>. <strong>POST:</strong> cuerpo JSON con <code class="rounded bg-zinc-200/80 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">Content-Type: application/json</code>. <strong>Errores 422:</strong> cuerpo con <code class="rounded bg-zinc-200/80 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">message</code> y <code class="rounded bg-zinc-200/80 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">errors</code> (objeto por campo).</p>
            </div>
            <h2 class="text-2xl font-bold">Endpoints disponibles</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">Los nombres de parámetros coinciden con la validación del servidor (Laravel FormRequest). Usa las tablas como referencia al armar tu integración.</p>
            <div class="flex flex-wrap gap-2 text-xs">
                <a href="#api-status" class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-900 dark:bg-slate-500/25 dark:text-slate-100">GET estado</a>
                <a href="#inventory" class="rounded-full bg-cyan-100 px-3 py-1 font-semibold text-cyan-900 dark:bg-cyan-500/20 dark:text-cyan-100">GET inventario</a>
                <a href="#external-branches" class="rounded-full bg-sky-100 px-3 py-1 font-semibold text-sky-900 dark:bg-sky-500/20 dark:text-sky-100">GET sucursales</a>
                <a href="#inventory-by-branch" class="rounded-full bg-teal-100 px-3 py-1 font-semibold text-teal-900 dark:bg-teal-500/20 dark:text-teal-100">GET inventario sucursal</a>
                <a href="#external-orders" class="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100">POST pedidos</a>
                <a href="#service-orders" class="rounded-full bg-violet-100 px-3 py-1 font-semibold text-violet-900 dark:bg-violet-500/20 dark:text-violet-100">POST orden servicio</a>
            </div>

            <details id="api-status" class="group rounded-3xl border border-zinc-200/80 bg-white/80 p-6 shadow-xl shadow-zinc-900/5 backdrop-blur-xl open:bg-white dark:border-white/10 dark:bg-white/5 dark:open:bg-zinc-900/40" open>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-300">GET</p>
                        <h3 class="font-mono text-base sm:text-lg">/api/external/status</h3>
                    </div>
                    <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-800 dark:bg-slate-600 dark:text-slate-100">Estado / salud</span>
                </summary>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">Comprueba en tiempo real si la API de integración está <strong>activa</strong>. Úsalo antes de reintentos o para monitoreo liviano: <strong>no requiere</strong> token Bearer ni <code class="rounded bg-zinc-100 px-1 font-mono dark:bg-zinc-800">partner_company</code>, así reduces tráfico innecesario a los endpoints que sí consultan base de datos.</p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-zinc-600 dark:text-zinc-300">
                    <li><strong>HTTP 200</strong> y <code class="rounded bg-zinc-100 px-1 font-mono text-xs dark:bg-zinc-800">"status": "active"</code> → servicio operativo.</li>
                    <li>Si no obtienes respuesta o hay error de red, evita spamear inventario/pedidos hasta recuperar conectividad.</li>
                    <li>Sin limite de peticiones por minuto en la aplicacion; un proxy o firewall delante del servidor puede imponer el suyo.</li>
                </ul>
                <p class="mt-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400">Respuesta HTTP 200 — ejemplo</p>
                <pre class="mt-2 overflow-auto rounded-xl bg-zinc-900 p-3 text-xs text-zinc-100"><code>{
  "status": "active",
  "api": "external",
  "message": "La API de integración para aliados está operativa.",
  "checked_at": "2026-03-24T18:30:00+00:00",
  "app": "{{ config('app.name') }}"
}</code></pre>
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">Ejemplo: <code class="rounded bg-zinc-100 px-1 font-mono dark:bg-zinc-800">curl -sS "{{ url('/api/external/status') }}"</code></p>
            </details>

            <details id="inventory" class="group rounded-3xl border border-zinc-200/80 bg-white/80 p-6 shadow-xl shadow-zinc-900/5 backdrop-blur-xl open:bg-white dark:border-white/10 dark:bg-white/5 dark:open:bg-zinc-900/40">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">GET</p>
                        <h3 class="font-mono text-base sm:text-lg">/api/external/inventory</h3>
                    </div>
                    <span class="rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">Inventario</span>
                </summary>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">Lista productos tipo <strong>medicamento</strong> activos cuyo arreglo de principio(s) activo(s) contiene el término buscado (búsqueda parcial, sin distinguir mayúsculas). Suma el inventario disponible entre sucursales.</p>
                <div class="mt-4 overflow-x-auto rounded-2xl border border-zinc-200/80 dark:border-white/10">
                    <table class="w-full min-w-[36rem] border-collapse text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50/90 dark:border-white/10 dark:bg-zinc-900/80">
                                <th class="px-3 py-2 font-semibold">Parámetro</th>
                                <th class="px-3 py-2 font-semibold">Dónde</th>
                                <th class="px-3 py-2 font-semibold">Tipo</th>
                                <th class="px-3 py-2 font-semibold">Obligatorio</th>
                                <th class="px-3 py-2 font-semibold">Reglas / notas</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-700 dark:text-zinc-200">
                            <tr class="border-b border-zinc-100 dark:border-white/5">
                                <td class="px-3 py-2 font-mono">partner_company</td>
                                <td class="px-3 py-2">Query string</td>
                                <td class="px-3 py-2">string</td>
                                <td class="px-3 py-2">Sí</td>
                                <td class="px-3 py-2">Código del aliado; debe existir en <code class="font-mono">partner_companies.code</code></td>
                            </tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5">
                                <td class="px-3 py-2 font-mono">active_ingredient</td>
                                <td class="px-3 py-2">Query string</td>
                                <td class="px-3 py-2">string</td>
                                <td class="px-3 py-2">Sí</td>
                                <td class="px-3 py-2">Mínimo 2 caracteres, máximo 2000. Ejemplo: <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">?partner_company=ALDO-2026-001&amp;active_ingredient=paracetamol</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">Ejemplo URL completa: <code class="rounded bg-zinc-100 px-1 font-mono dark:bg-zinc-800">GET /api/external/inventory?partner_company=ALDO-2026-001&amp;active_ingredient=paracetamol</code></p>
                <h4 class="mt-6 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Campos en cada elemento de <code class="font-mono">data[]</code></h4>
                <div class="mt-2 overflow-x-auto rounded-2xl border border-zinc-200/80 dark:border-white/10">
                    <table class="w-full min-w-[36rem] border-collapse text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50/90 dark:border-white/10 dark:bg-zinc-900/80">
                                <th class="px-3 py-2 font-semibold">Campo</th>
                                <th class="px-3 py-2 font-semibold">Tipo</th>
                                <th class="px-3 py-2 font-semibold">Descripción</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-700 dark:text-zinc-200">
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">id</td><td class="px-3 py-2">integer</td><td class="px-3 py-2">ID del producto</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">sku</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">SKU del producto</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">name</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Nombre comercial</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">barcode</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Código de barras</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">active_ingredient</td><td class="px-3 py-2">array|string</td><td class="px-3 py-2">Principio(s) activo(s) según catálogo</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">concentration</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Concentración</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">presentation</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Presentación</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">presentation_type</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Tipo de presentación</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">sale_price</td><td class="px-3 py-2">number</td><td class="px-3 py-2">Precio de venta</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">requires_prescription</td><td class="px-3 py-2">boolean</td><td class="px-3 py-2">Requiere fórmula</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">is_controlled_substance</td><td class="px-3 py-2">boolean</td><td class="px-3 py-2">Sustancia controlada</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">health_registration_number</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Registro sanitario</td></tr>
                            <tr><td class="px-3 py-2 font-mono">total_available_quantity</td><td class="px-3 py-2">number</td><td class="px-3 py-2">Suma de cantidad disponible en inventarios</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400">Respuesta HTTP 200 — cuerpo de ejemplo</p>
                <pre class="mt-2 overflow-auto rounded-xl bg-zinc-900 p-3 text-xs text-zinc-100"><code>{
  "data": [
    {
      "id": 15,
      "sku": "MED-500-A",
      "name": "Acetaminofen 500 mg",
      "barcode": "001234",
      "active_ingredient": ["Paracetamol"],
      "concentration": "500 mg",
      "presentation": "Tableta",
      "presentation_type": "Tableta",
      "sale_price": 1200.5,
      "requires_prescription": false,
      "is_controlled_substance": false,
      "health_registration_number": null,
      "total_available_quantity": 90
    }
  ]
}</code></pre>
            </details>

            <details id="external-branches" class="group rounded-3xl border border-zinc-200/80 bg-white/80 p-6 shadow-xl shadow-zinc-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-white/5 dark:open:bg-zinc-900/40">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">GET</p>
                        <h3 class="font-mono text-base sm:text-lg">/api/external/branches</h3>
                    </div>
                    <span class="rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">Sucursales</span>
                </summary>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">Lista las <strong>sucursales activas</strong> registradas en el sistema (identificador, código interno, nombre y ubicación básica). Úsalo para obtener <code class="rounded bg-zinc-100 px-1 font-mono text-xs dark:bg-zinc-800">branch_id</code> antes de consultar <a href="#inventory-by-branch" class="font-semibold text-cyan-700 underline dark:text-cyan-300">inventario por sucursal</a>.</p>
                <div class="mt-4 overflow-x-auto rounded-2xl border border-zinc-200/80 dark:border-white/10">
                    <table class="w-full min-w-[32rem] border-collapse text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50/90 dark:border-white/10 dark:bg-zinc-900/80">
                                <th class="px-3 py-2 font-semibold">Parámetro</th>
                                <th class="px-3 py-2 font-semibold">Dónde</th>
                                <th class="px-3 py-2 font-semibold">Tipo</th>
                                <th class="px-3 py-2 font-semibold">Obligatorio</th>
                                <th class="px-3 py-2 font-semibold">Reglas / notas</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-700 dark:text-zinc-200">
                            <tr class="border-b border-zinc-100 dark:border-white/5">
                                <td class="px-3 py-2 font-mono">partner_company</td>
                                <td class="px-3 py-2">Query string</td>
                                <td class="px-3 py-2">string</td>
                                <td class="px-3 py-2">Sí</td>
                                <td class="px-3 py-2">Código del aliado; debe existir en <code class="font-mono">partner_companies.code</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">Ejemplo: <code class="rounded bg-zinc-100 px-1 font-mono dark:bg-zinc-800">GET /api/external/branches?partner_company=ALDO-2026-001</code></p>
                <h4 class="mt-6 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Campos en cada elemento de <code class="font-mono">data[]</code></h4>
                <div class="mt-2 overflow-x-auto rounded-2xl border border-zinc-200/80 dark:border-white/10">
                    <table class="w-full min-w-[28rem] border-collapse text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50/90 dark:border-white/10 dark:bg-zinc-900/80">
                                <th class="px-3 py-2 font-semibold">Campo</th>
                                <th class="px-3 py-2 font-semibold">Tipo</th>
                                <th class="px-3 py-2 font-semibold">Descripción</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-700 dark:text-zinc-200">
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">id</td><td class="px-3 py-2">integer</td><td class="px-3 py-2">ID de la sucursal (usar como <code class="font-mono">branch_id</code> en otros endpoints)</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">code</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Código interno (p. ej. <code class="font-mono">SUC-1</code>)</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">name</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Nombre comercial de la sucursal</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">city</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Ciudad</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">state</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Departamento / estado</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">country</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">País</td></tr>
                            <tr><td class="px-3 py-2 font-mono">is_headquarters</td><td class="px-3 py-2">boolean</td><td class="px-3 py-2">Indica si es sede principal</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400">Respuesta HTTP 200 — ejemplo</p>
                <pre class="mt-2 overflow-auto rounded-xl bg-zinc-900 p-3 text-xs text-zinc-100"><code>{
  "data": [
    {
      "id": 1,
      "code": "SUC-1",
      "name": "Farmacia Centro",
      "city": "Bogotá",
      "state": "Cundinamarca",
      "country": "CO",
      "is_headquarters": true
    }
  ]
}</code></pre>
            </details>

            <details id="inventory-by-branch" class="group rounded-3xl border border-zinc-200/80 bg-white/80 p-6 shadow-xl shadow-zinc-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-white/5 dark:open:bg-zinc-900/40">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">GET</p>
                        <h3 class="font-mono text-base sm:text-lg">/api/external/inventory-by-branch</h3>
                    </div>
                    <span class="rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">Inventario / sucursal</span>
                </summary>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">Devuelve todas las <strong>filas de inventario</strong> de una sucursal concreta (existencias, reservado, precios e impuestos a nivel sucursal) y datos del producto asociado. La sucursal debe estar <strong>activa</strong>.</p>
                <div class="mt-4 overflow-x-auto rounded-2xl border border-zinc-200/80 dark:border-white/10">
                    <table class="w-full min-w-[36rem] border-collapse text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50/90 dark:border-white/10 dark:bg-zinc-900/80">
                                <th class="px-3 py-2 font-semibold">Parámetro</th>
                                <th class="px-3 py-2 font-semibold">Dónde</th>
                                <th class="px-3 py-2 font-semibold">Tipo</th>
                                <th class="px-3 py-2 font-semibold">Obligatorio</th>
                                <th class="px-3 py-2 font-semibold">Reglas / notas</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-700 dark:text-zinc-200">
                            <tr class="border-b border-zinc-100 dark:border-white/5">
                                <td class="px-3 py-2 font-mono">partner_company</td>
                                <td class="px-3 py-2">Query string</td>
                                <td class="px-3 py-2">string</td>
                                <td class="px-3 py-2">Sí</td>
                                <td class="px-3 py-2">Código del aliado; debe existir en <code class="font-mono">partner_companies.code</code></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-mono">branch_id</td>
                                <td class="px-3 py-2">Query string</td>
                                <td class="px-3 py-2">integer</td>
                                <td class="px-3 py-2">Sí</td>
                                <td class="px-3 py-2">ID de sucursal <strong>activa</strong> (p. ej. obtenido con <code class="font-mono">GET /branches</code>)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">Ejemplo: <code class="rounded bg-zinc-100 px-1 font-mono dark:bg-zinc-800">GET /api/external/inventory-by-branch?partner_company=ALDO-2026-001&amp;branch_id=1</code></p>
                <h4 class="mt-6 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Objeto <code class="font-mono">branch</code> (cabecera)</h4>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300"><code class="font-mono">id</code>, <code class="font-mono">code</code>, <code class="font-mono">name</code>, <code class="font-mono">city</code>, <code class="font-mono">state</code>, <code class="font-mono">country</code>.</p>
                <h4 class="mt-4 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Campos en cada elemento de <code class="font-mono">data[]</code></h4>
                <div class="mt-2 overflow-x-auto rounded-2xl border border-zinc-200/80 dark:border-white/10">
                    <table class="w-full min-w-[36rem] border-collapse text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50/90 dark:border-white/10 dark:bg-zinc-900/80">
                                <th class="px-3 py-2 font-semibold">Campo</th>
                                <th class="px-3 py-2 font-semibold">Tipo</th>
                                <th class="px-3 py-2 font-semibold">Descripción</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-700 dark:text-zinc-200">
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">inventory_id</td><td class="px-3 py-2">integer</td><td class="px-3 py-2">ID de la fila de inventario</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">product_id</td><td class="px-3 py-2">integer</td><td class="px-3 py-2">ID del producto</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">sku</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">SKU</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">name</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Nombre del producto (null si el producto ya no existe)</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">barcode</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Código de barras</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">product_category_id</td><td class="px-3 py-2">integer|null</td><td class="px-3 py-2">ID de categoría en catálogo</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">product_category_name</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Nombre de la categoría</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">active_ingredient</td><td class="px-3 py-2">array|null</td><td class="px-3 py-2">Principio(s) activo(s) según catálogo</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">concentration</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Concentración</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">presentation_type</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">Tipo de presentación</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">product_is_active</td><td class="px-3 py-2">boolean|null</td><td class="px-3 py-2">Si el producto está activo en catálogo</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">quantity</td><td class="px-3 py-2">number</td><td class="px-3 py-2">Existencias en sucursal</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">reserved_quantity</td><td class="px-3 py-2">number</td><td class="px-3 py-2">Cantidad reservada</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">available_quantity</td><td class="px-3 py-2">number</td><td class="px-3 py-2">Disponible para venta (según reglas de stock negativo)</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">sale_price</td><td class="px-3 py-2">number</td><td class="px-3 py-2">Precio de lista en sucursal</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">effective_sale_unit_price</td><td class="px-3 py-2">number</td><td class="px-3 py-2">Precio unitario tras descuento % de la sucursal</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">discount_percent</td><td class="px-3 py-2">number</td><td class="px-3 py-2">Descuento % en sucursal</td></tr>
                            <tr><td class="px-3 py-2 font-mono">allow_negative_stock</td><td class="px-3 py-2">boolean</td><td class="px-3 py-2">Permite saldo negativo en esta fila</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400">Respuesta HTTP 200 — ejemplo (fragmento)</p>
                <pre class="mt-2 overflow-auto rounded-xl bg-zinc-900 p-3 text-xs text-zinc-100"><code>{
  "branch": {
    "id": 1,
    "code": "SUC-1",
    "name": "Farmacia Centro",
    "city": "Bogotá",
    "state": "Cundinamarca",
    "country": "CO"
  },
  "data": [
    {
      "inventory_id": 10,
      "product_id": 15,
      "sku": "MED-500-A",
      "name": "Acetaminofen 500 mg",
      "barcode": "001234",
      "product_category_id": 3,
      "product_category_name": "Medicamentos",
      "active_ingredient": ["Paracetamol"],
      "concentration": "500 mg",
      "presentation_type": "Tableta",
      "product_is_active": true,
      "quantity": 100,
      "reserved_quantity": 5,
      "available_quantity": 95,
      "sale_price": 1200.5,
      "effective_sale_unit_price": 1140.48,
      "discount_percent": 5,
      "allow_negative_stock": false
    }
  ]
}</code></pre>
            </details>

            <details id="external-orders" class="group rounded-3xl border border-zinc-200/80 bg-white/80 p-6 shadow-xl shadow-zinc-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-white/5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">POST</p>
                        <h3 class="font-mono text-base sm:text-lg">/api/external/orders</h3>
                    </div>
                    <span class="rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">Pedidos</span>
                </summary>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">Crea un <strong>pedido de venta</strong> con líneas de producto, precios y descuentos por línea. El servidor recalcula subtotales y totales (sin IVA por línea; <code class="rounded bg-zinc-100 px-1 font-mono text-xs dark:bg-zinc-800">tax_total</code> queda en 0).</p>
                <h4 class="mt-4 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Cuerpo JSON — campos raíz</h4>
                <div class="mt-2 overflow-x-auto rounded-2xl border border-zinc-200/80 dark:border-white/10">
                    <table class="w-full min-w-[40rem] border-collapse text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50/90 dark:border-white/10 dark:bg-zinc-900/80">
                                <th class="px-3 py-2 font-semibold">Parámetro</th>
                                <th class="px-3 py-2 font-semibold">Tipo</th>
                                <th class="px-3 py-2 font-semibold">Obligatorio</th>
                                <th class="px-3 py-2 font-semibold">Validación / notas</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-700 dark:text-zinc-200">
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">partner_company</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Código del aliado; debe existir en <code class="font-mono">partner_companies.code</code></td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">client_id</td><td class="px-3 py-2">integer</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Debe existir en tabla <code class="font-mono">clients</code></td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">items</td><td class="px-3 py-2">array</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Mínimo 1 elemento</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">order_number</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Si se envía, único en <code class="font-mono">orders.order_number</code></td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">branch_id</td><td class="px-3 py-2">integer|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">ID de sucursal existente en <code class="font-mono">branches</code></td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">status</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Enum: <code class="text-[11px]">pendiente</code>, <code class="text-[11px]">en-proceso</code>, <code class="text-[11px]">finalizado</code></td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">convenio_type</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Enum: <code class="text-[11px]">particular</code>, <code class="text-[11px]">seguro-privado</code>, <code class="text-[11px]">eps</code>, <code class="text-[11px]">medicina-prepagada</code>, <code class="text-[11px]">convenio-corporativo</code>, <code class="text-[11px]">otro</code></td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">convenio_partner_name</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Máx. 255</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">convenio_reference</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Máx. 255</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">convenio_notes</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Texto libre</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">delivery_recipient_name</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Máx. 255</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">delivery_phone</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Máx. 40</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">delivery_address</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Máx. 255</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">delivery_city</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Máx. 100</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">delivery_state</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Máx. 100</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">delivery_notes</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Texto libre</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">scheduled_delivery_at</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Fecha/hora parseable</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">dispatched_at</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Fecha/hora parseable</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">delivered_at</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Fecha/hora parseable</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">delivery_assignee</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Máx. 255</td></tr>
                            <tr><td class="px-3 py-2 font-mono">notes</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Notas del pedido</td></tr>
                        </tbody>
                    </table>
                </div>
                <h4 class="mt-6 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Cada elemento de <code class="font-mono">items[]</code></h4>
                <div class="mt-2 overflow-x-auto rounded-2xl border border-zinc-200/80 dark:border-white/10">
                    <table class="w-full min-w-[40rem] border-collapse text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50/90 dark:border-white/10 dark:bg-zinc-900/80">
                                <th class="px-3 py-2 font-semibold">Parámetro</th>
                                <th class="px-3 py-2 font-semibold">Tipo</th>
                                <th class="px-3 py-2 font-semibold">Obligatorio</th>
                                <th class="px-3 py-2 font-semibold">Validación / notas</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-700 dark:text-zinc-200">
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">product_id</td><td class="px-3 py-2">integer</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Debe existir en <code class="font-mono">products</code></td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">quantity</td><td class="px-3 py-2">number</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Mayor que 0</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">unit_price</td><td class="px-3 py-2">number</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">≥ 0</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">inventory_id</td><td class="px-3 py-2">integer|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Debe existir en <code class="font-mono">inventories</code></td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">discount_amount</td><td class="px-3 py-2">number|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">≥ 0</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">product_name_snapshot</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Máx. 255</td></tr>
                            <tr><td class="px-3 py-2 font-mono">sku_snapshot</td><td class="px-3 py-2">string|null</td><td class="px-3 py-2">No</td><td class="px-3 py-2">Máx. 255</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400">Respuesta HTTP 201 — ejemplo</p>
                <pre class="mt-2 overflow-auto rounded-xl bg-zinc-900 p-3 text-xs text-zinc-100"><code>{
  "message": "Pedido creado correctamente.",
  "data": {
    "order_id": 1002,
    "order_number": "EXT-20260322123000-4321",
    "partner_company": "ALDO-2026-001",
    "status": "pendiente",
    "items_count": 1,
    "subtotal": 24000,
    "tax_total": 0,
    "discount_total": 0,
    "total": 24000
  }
}</code></pre>
            </details>

            <details id="service-orders" class="group rounded-3xl border border-zinc-200/80 bg-white/80 p-6 shadow-xl shadow-zinc-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-white/5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">POST</p>
                        <h3 class="font-mono text-base sm:text-lg">/api/external/service-orders</h3>
                    </div>
                    <span class="rounded-full bg-violet-200 px-3 py-1 text-xs font-semibold text-violet-900 dark:bg-violet-500/30 dark:text-violet-100">Orden de servicio</span>
                </summary>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">Registra una <strong>orden de servicio</strong> para un paciente y medicamentos con indicaciones. La fecha de emisión <code class="rounded bg-zinc-100 px-1 font-mono dark:bg-zinc-800">ordered_at</code> la asigna el servidor al crear el registro (no se envía en el JSON).</p>
                <div class="mt-4 overflow-x-auto rounded-2xl border border-zinc-200/80 dark:border-white/10">
                    <table class="w-full min-w-[40rem] border-collapse text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50/90 dark:border-white/10 dark:bg-zinc-900/80">
                                <th class="px-3 py-2 font-semibold">Parámetro</th>
                                <th class="px-3 py-2 font-semibold">Tipo</th>
                                <th class="px-3 py-2 font-semibold">Obligatorio</th>
                                <th class="px-3 py-2 font-semibold">Validación / notas</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-700 dark:text-zinc-200">
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">partner_company</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Código del aliado; debe existir en <code class="font-mono">partner_companies.code</code></td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">status</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Máx. 255 (ej. <code class="text-[11px]">en-proceso</code>, <code class="text-[11px]">borrador</code>)</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">priority</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Máx. 255 (ej. <code class="text-[11px]">media</code>, <code class="text-[11px]">alta</code>)</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">service_type</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Máx. 255</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">external_reference</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Máx. 255 — referencia en el sistema del aliado</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">patient_name</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Máx. 255</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">patient_document</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Máx. 255</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">patient_phone</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Solo dígitos; el servidor elimina espacios y símbolos si los envías</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">patient_email</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Correo válido, máx. 255</td></tr>
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">diagnosis</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Texto (diagnóstico o motivo)</td></tr>
                            <tr><td class="px-3 py-2 font-mono">items</td><td class="px-3 py-2">array</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Mínimo 1 objeto; ver tabla siguiente</td></tr>
                        </tbody>
                    </table>
                </div>
                <h4 class="mt-6 text-sm font-semibold text-zinc-800 dark:text-zinc-100">Cada elemento de <code class="font-mono">items[]</code></h4>
                <div class="mt-2 overflow-x-auto rounded-2xl border border-zinc-200/80 dark:border-white/10">
                    <table class="w-full min-w-[32rem] border-collapse text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50/90 dark:border-white/10 dark:bg-zinc-900/80">
                                <th class="px-3 py-2 font-semibold">Parámetro</th>
                                <th class="px-3 py-2 font-semibold">Tipo</th>
                                <th class="px-3 py-2 font-semibold">Obligatorio</th>
                                <th class="px-3 py-2 font-semibold">Validación</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-700 dark:text-zinc-200">
                            <tr class="border-b border-zinc-100 dark:border-white/5"><td class="px-3 py-2 font-mono">name</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Máx. 500 — nombre del medicamento</td></tr>
                            <tr><td class="px-3 py-2 font-mono">indicacion</td><td class="px-3 py-2">string</td><td class="px-3 py-2">Sí</td><td class="px-3 py-2">Mínimo 1 carácter — posología / indicaciones</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400">Respuesta HTTP 201 — ejemplo</p>
                <pre class="mt-2 overflow-auto rounded-xl bg-zinc-900 p-3 text-xs text-zinc-100"><code>{
  "message": "Orden de servicio registrada correctamente.",
  "data": {
    "order_service_id": 42,
    "service_order_number": "ORD-0042",
    "partner_company_code": "ALDO-2026-001",
    "status": "en-proceso",
    "priority": "media",
    "ordered_at": "2026-03-24T15:30:00-05:00",
    "items_count": 1
  }
}</code></pre>
            </details>
        </section>

        <section id="playground" class="rounded-3xl border border-zinc-200/80 bg-white/80 p-6 shadow-xl shadow-zinc-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-white/5 sm:p-8">
            <h2 class="text-2xl font-bold">Playground interactivo</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Para <strong>GET estado</strong> no necesitas token. Para el resto, pega tu <strong>token Bearer</strong> del panel y sustituye <code class="rounded bg-zinc-200/80 px-1 font-mono text-xs dark:bg-zinc-700">ALDO-2026-001</code> por el <strong>código real</strong> de compañía aliada (<code class="rounded bg-zinc-200/80 px-1 font-mono text-xs dark:bg-zinc-700">partner_companies.code</code>). Los ejemplos usan la <strong>misma URL base</strong> que esta página.</p>
            <p id="pg-base-hint" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400"></p>
            <p id="pg-param-hint" class="mt-2 rounded-xl border border-cyan-200/80 bg-cyan-50/90 px-4 py-3 text-xs leading-relaxed text-cyan-950 dark:border-cyan-500/25 dark:bg-cyan-500/10 dark:text-cyan-100"></p>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <label class="space-y-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Token Bearer</span>
                    <input id="pg-token" type="password" autocomplete="off" placeholder="Pega aqui el token (fd_...)" class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-3 font-mono text-sm shadow-sm outline-none ring-cyan-500 transition focus:ring-2 dark:border-white/10 dark:bg-zinc-900">
                </label>
                <label class="space-y-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Endpoint</span>
                    <select id="pg-endpoint" class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm shadow-sm outline-none ring-cyan-500 transition focus:ring-2 dark:border-white/10 dark:bg-zinc-900">
                        <option value="status">GET estado de la API (sin token)</option>
                        <option value="inventory">GET inventario (por principio activo)</option>
                        <option value="branches">GET listar sucursales activas</option>
                        <option value="inventory_by_branch">GET inventario por sucursal</option>
                        <option value="orders">POST crear pedido</option>
                        <option value="service_orders">POST orden de servicio (aliado)</option>
                    </select>
                </label>
            </div>

            <div class="mt-4 flex flex-wrap gap-2" role="tablist" aria-label="Lenguajes">
                <button type="button" class="lang-tab rounded-xl bg-zinc-900 px-3 py-2 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900" data-lang="curl">cURL</button>
                <button type="button" class="lang-tab rounded-xl border border-zinc-200 px-3 py-2 text-xs font-semibold dark:border-white/10" data-lang="javascript">JavaScript</button>
                <button type="button" class="lang-tab rounded-xl border border-zinc-200 px-3 py-2 text-xs font-semibold dark:border-white/10" data-lang="python">Python</button>
                <button type="button" class="lang-tab rounded-xl border border-zinc-200 px-3 py-2 text-xs font-semibold dark:border-white/10" data-lang="php">PHP (cURL)</button>
                <button type="button" class="lang-tab rounded-xl border border-zinc-200 px-3 py-2 text-xs font-semibold dark:border-white/10" data-lang="guzzle">PHP (Guzzle)</button>
            </div>

            <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-200/80 dark:border-white/10">
                <pre class="overflow-auto bg-zinc-900 p-4 text-xs text-zinc-100 sm:text-sm"><code id="playground-code"></code></pre>
            </div>
            <button id="copy-playground" class="mt-3 rounded-xl bg-zinc-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">Copiar codigo</button>
        </section>
    </main>

    <script>
        const root = document.documentElement;
        const themeToggle = document.getElementById('theme-toggle');
        const themeLabel = document.getElementById('theme-label');
        const storedTheme = localStorage.getItem('docs-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        function applyTheme(theme) {
            const isDark = theme === 'dark';
            root.classList.toggle('dark', isDark);
            themeLabel.textContent = isDark ? 'Modo claro' : 'Modo oscuro';
        }

        applyTheme(storedTheme ?? (prefersDark ? 'dark' : 'light'));

        themeToggle.addEventListener('click', () => {
            const next = root.classList.contains('dark') ? 'light' : 'dark';
            localStorage.setItem('docs-theme', next);
            applyTheme(next);
        });

        document.querySelectorAll('.copy-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-copy-target');
                const code = document.getElementById(targetId);
                if (!code) return;
                navigator.clipboard.writeText(code.textContent.trim());
                const oldText = button.textContent;
                button.textContent = 'Copiado';
                setTimeout(() => button.textContent = oldText, 1400);
            });
        });

        const endpointSelect = document.getElementById('pg-endpoint');
        const tokenInput = document.getElementById('pg-token');
        const baseHint = document.getElementById('pg-base-hint');
        const paramHint = document.getElementById('pg-param-hint');
        const codeBlock = document.getElementById('playground-code');
        const copyPlayground = document.getElementById('copy-playground');
        const langTabs = Array.from(document.querySelectorAll('.lang-tab'));
        let selectedLang = 'curl';

        function playgroundBaseUrl() {
            return window.location.origin.replace(/\/$/, '');
        }

        const endpointParamHints = {
            status: '<strong>GET /api/external/status</strong> — sin query, sin <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">Authorization</code>, sin <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">partner_company</code>. Respuesta: <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">status: active</code> si todo está bien.',
            inventory: '<strong>GET /api/external/inventory</strong> — query: <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">partner_company</code> (código en partner_companies, oblig.) y <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">active_ingredient</code> (string, oblig., mín. 2, máx. 2000).',
            branches: '<strong>GET /api/external/branches</strong> — query obligatorio: <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">partner_company</code>. Lista sucursales <strong>activas</strong> con <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">id</code>, <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">code</code>, nombre y ubicación.',
            inventory_by_branch: '<strong>GET /api/external/inventory-by-branch</strong> — query: <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">partner_company</code> (oblig.) y <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">branch_id</code> (entero, sucursal <strong>activa</strong>). Respuesta: objeto <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">branch</code> + arreglo <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">data</code> con líneas de inventario.',
            orders: '<strong>POST /api/external/orders</strong> — JSON raíz: <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">partner_company</code> (código oblig.), <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">client_id</code> (int, oblig.) e <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">items[]</code> con <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">product_id</code>, <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">quantity</code> &gt; 0, <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">unit_price</code> ≥ 0. El ejemplo incluye campos opcionales frecuentes.',
            service_orders: '<strong>POST /api/external/service-orders</strong> — JSON: <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">partner_company</code> (código en partner_companies), datos de paciente, <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">diagnosis</code>, <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">items[]</code> con <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">name</code> e <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">indicacion</code>. No envíes <code class="rounded bg-white/60 px-1 font-mono dark:bg-black/30">ordered_at</code> (lo asigna el servidor).',
        };

        const snippets = {
            status: {
                curl: (baseUrl, token) => `# === GET /api/external/status (sin token, sin partner_company) ===
# Comprueba que la API esta activa antes de llamadas pesadas.
curl -sS -X GET "${baseUrl}/api/external/status" \\
  -H "Accept: application/json" \\
  -w "\\nHTTP %{http_code}\\n"`,
                javascript: (baseUrl, token) => `// === GET /api/external/status ===
// No requiere Authorization ni partner_company.

const baseUrl = ${JSON.stringify(baseUrl)};

const response = await fetch(\`\${baseUrl}/api/external/status\`, {
  method: 'GET',
  headers: { Accept: 'application/json' },
});

const bodyText = await response.text();
let data;
try {
  data = JSON.parse(bodyText);
} catch {
  data = bodyText;
}

console.log('HTTP', response.status);
console.log(data);`,
                python: (baseUrl, token) => `"""GET /api/external/status — sin token."""
import requests

BASE_URL = ${JSON.stringify(baseUrl)}

response = requests.get(
    f"{BASE_URL}/api/external/status",
    headers={"Accept": "application/json"},
    timeout=10,
)
print(response.status_code)
print(response.json())`,
                php: (baseUrl, token) => `&lt;?php
/**
 * GET /api/external/status — sin token.
 */

$baseUrl = ${JSON.stringify(baseUrl)};

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

echo 'HTTP ' . $status . PHP_EOL;
echo $body . PHP_EOL;`,
                guzzle: (baseUrl, token) => `&lt;?php
/**
 * GET /api/external/status — Guzzle
 */

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\\Client;
use GuzzleHttp\\Exception\\RequestException;

$baseUrl = ${JSON.stringify(baseUrl)};

$client = new Client([
    'base_uri' => rtrim($baseUrl, '/') . '/',
    'timeout' => 10,
]);

try {
    $response = $client->get('api/external/status', [
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);

    echo 'HTTP ' . $response->getStatusCode() . PHP_EOL;
    echo (string) $response->getBody() . PHP_EOL;
} catch (RequestException $e) {
    $res = $e->hasResponse() ? $e->getResponse() : null;
    echo $e->getMessage() . PHP_EOL;
    if ($res) {
        echo (string) $res->getBody() . PHP_EOL;
    }
    exit(1);
}`,
            },
            inventory: {
                curl: (baseUrl, token) => `# === GET /api/external/inventory (parametros reales) ===
# Query string OBLIGATORIO:
#   partner_company    string  codigo en partner_companies.code
#   active_ingredient  string  min 2, max 2000 caracteres
#
# Ejemplo: buscar por "paracetamol" (cambia partner_company y el termino segun tu caso).
BASE="${baseUrl}"
TOKEN="${token}"

curl -sS -X GET "${baseUrl}/api/external/inventory?partner_company=ALDO-2026-001&active_ingredient=paracetamol" \\
  -H "Accept: application/json" \\
  -H "Authorization: Bearer ${token}" \\
  -w "\\nHTTP %{http_code}\\n"`,
                javascript: (baseUrl, token) => `// === GET /api/external/inventory ===
// Query obligatorio: partner_company, active_ingredient (string min 2, max 2000)

const baseUrl = ${JSON.stringify(baseUrl)};
const token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};

const params = new URLSearchParams({
  partner_company: 'ALDO-2026-001',
  active_ingredient: 'paracetamol',
});
const url = \`\${baseUrl}/api/external/inventory?\${params.toString()}\`;

const response = await fetch(url, {
  method: 'GET',
  headers: {
    Accept: 'application/json',
    Authorization: \`Bearer \${token}\`,
  },
});

const bodyText = await response.text();
let data;
try {
  data = JSON.parse(bodyText);
} catch {
  data = bodyText;
}

console.log('HTTP', response.status);
console.log(data);`,
                python: (baseUrl, token) => `"""GET /api/external/inventory
Query obligatorio: partner_company, active_ingredient (str, min 2, max 2000).
pip install requests
"""
import requests

BASE_URL = ${JSON.stringify(baseUrl)}
TOKEN = ${JSON.stringify(token || 'TU_TOKEN_AQUI')}

session = requests.Session()
session.headers.update({
    "Accept": "application/json",
    "Authorization": f"Bearer {TOKEN}",
})

params = {"partner_company": "ALDO-2026-001", "active_ingredient": "paracetamol"}
response = session.get(f"{BASE_URL}/api/external/inventory", params=params, timeout=30)
response.raise_for_status()
print(response.status_code)
print(response.json())`,
                php: (baseUrl, token) => `&lt;?php
/**
 * GET /api/external/inventory
 * Query obligatorio: partner_company, active_ingredient (string, min 2, max 2000)
 */

$baseUrl = ${JSON.stringify(baseUrl)};
$token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};

$query = http_build_query([
    'partner_company' => 'ALDO-2026-001',
    'active_ingredient' => 'paracetamol',
]);

$url = rtrim($baseUrl, '/') . '/api/external/inventory?' . $query;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
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

echo 'HTTP ' . $status . PHP_EOL;
echo $body . PHP_EOL;`,
                guzzle: (baseUrl, token) => `&lt;?php
/**
 * GET /api/external/inventory — Guzzle
 * Query: partner_company, active_ingredient (requerido, min 2 caracteres)
 * composer require guzzlehttp/guzzle:^7
 */

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\\Client;
use GuzzleHttp\\Exception\\RequestException;

$baseUrl = ${JSON.stringify(baseUrl)};
$token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};

$client = new Client([
    'base_uri' => rtrim($baseUrl, '/') . '/',
    'timeout' => 30,
]);

try {
    $response = $client->get('api/external/inventory', [
        'query' => [
            'partner_company' => 'ALDO-2026-001',
            'active_ingredient' => 'paracetamol',
        ],
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ],
    ]);

    echo 'HTTP ' . $response->getStatusCode() . PHP_EOL;
    echo (string) $response->getBody() . PHP_EOL;
} catch (RequestException $e) {
    $res = $e->hasResponse() ? $e->getResponse() : null;
    echo $e->getMessage() . PHP_EOL;
    if ($res) {
        echo (string) $res->getBody() . PHP_EOL;
    }
    exit(1);
}`,
            },
            branches: {
                curl: (baseUrl, token) => `# === GET /api/external/branches ===
# Query obligatorio: partner_company (codigo en partner_companies.code)
curl -sS -X GET "${baseUrl}/api/external/branches?partner_company=ALDO-2026-001" \\
  -H "Accept: application/json" \\
  -H "Authorization: Bearer ${token}" \\
  -w "\\nHTTP %{http_code}\\n"`,
                javascript: (baseUrl, token) => `// === GET /api/external/branches ===
const baseUrl = ${JSON.stringify(baseUrl)};
const token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};
const params = new URLSearchParams({ partner_company: 'ALDO-2026-001' });
const url = \`\${baseUrl}/api/external/branches?\${params.toString()}\`;
const response = await fetch(url, {
  method: 'GET',
  headers: { Accept: 'application/json', Authorization: \`Bearer \${token}\` },
});
const bodyText = await response.text();
let data;
try { data = JSON.parse(bodyText); } catch { data = bodyText; }
console.log('HTTP', response.status);
console.log(data);`,
                python: (baseUrl, token) => `"""GET /api/external/branches — partner_company obligatorio."""
import requests
BASE_URL = ${JSON.stringify(baseUrl)}
TOKEN = ${JSON.stringify(token || 'TU_TOKEN_AQUI')}
session = requests.Session()
session.headers.update({"Accept": "application/json", "Authorization": f"Bearer {TOKEN}"})
params = {"partner_company": "ALDO-2026-001"}
response = session.get(f"{BASE_URL}/api/external/branches", params=params, timeout=30)
print(response.status_code)
print(response.json())`,
                php: (baseUrl, token) => `&lt;?php
/**
 * GET /api/external/branches — query: partner_company
 */
$baseUrl = ${JSON.stringify(baseUrl)};
$token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};
$query = http_build_query(['partner_company' => 'ALDO-2026-001']);
$url = rtrim($baseUrl, '/') . '/api/external/branches?' . $query;
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ],
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
echo 'HTTP ' . $status . PHP_EOL;
echo $body . PHP_EOL;`,
                guzzle: (baseUrl, token) => `&lt;?php
require __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\\Client;
use GuzzleHttp\\Exception\\RequestException;
$baseUrl = ${JSON.stringify(baseUrl)};
$token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};
$client = new Client(['base_uri' => rtrim($baseUrl, '/') . '/', 'timeout' => 30]);
try {
    $response = $client->get('api/external/branches', [
        'query' => ['partner_company' => 'ALDO-2026-001'],
        'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $token],
    ]);
    echo 'HTTP ' . $response->getStatusCode() . PHP_EOL;
    echo (string) $response->getBody() . PHP_EOL;
} catch (RequestException $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}`,
            },
            inventory_by_branch: {
                curl: (baseUrl, token) => `# === GET /api/external/inventory-by-branch ===
# Query: partner_company (oblig.), branch_id entero sucursal activa
curl -sS -X GET "${baseUrl}/api/external/inventory-by-branch?partner_company=ALDO-2026-001&branch_id=1" \\
  -H "Accept: application/json" \\
  -H "Authorization: Bearer ${token}" \\
  -w "\\nHTTP %{http_code}\\n"`,
                javascript: (baseUrl, token) => `// === GET /api/external/inventory-by-branch ===
const baseUrl = ${JSON.stringify(baseUrl)};
const token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};
const params = new URLSearchParams({
  partner_company: 'ALDO-2026-001',
  branch_id: '1',
});
const url = \`\${baseUrl}/api/external/inventory-by-branch?\${params.toString()}\`;
const response = await fetch(url, {
  method: 'GET',
  headers: { Accept: 'application/json', Authorization: \`Bearer \${token}\` },
});
const bodyText = await response.text();
let data;
try { data = JSON.parse(bodyText); } catch { data = bodyText; }
console.log('HTTP', response.status);
console.log(data);`,
                python: (baseUrl, token) => `"""GET /api/external/inventory-by-branch — partner_company + branch_id."""
import requests
BASE_URL = ${JSON.stringify(baseUrl)}
TOKEN = ${JSON.stringify(token || 'TU_TOKEN_AQUI')}
session = requests.Session()
session.headers.update({"Accept": "application/json", "Authorization": f"Bearer {TOKEN}"})
params = {"partner_company": "ALDO-2026-001", "branch_id": 1}
response = session.get(f"{BASE_URL}/api/external/inventory-by-branch", params=params, timeout=30)
print(response.status_code)
print(response.json())`,
                php: (baseUrl, token) => `&lt;?php
/**
 * GET /api/external/inventory-by-branch
 */
$baseUrl = ${JSON.stringify(baseUrl)};
$token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};
$query = http_build_query([
    'partner_company' => 'ALDO-2026-001',
    'branch_id' => 1,
]);
$url = rtrim($baseUrl, '/') . '/api/external/inventory-by-branch?' . $query;
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ],
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
echo 'HTTP ' . $status . PHP_EOL;
echo $body . PHP_EOL;`,
                guzzle: (baseUrl, token) => `&lt;?php
require __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\\Client;
use GuzzleHttp\\Exception\\RequestException;
$baseUrl = ${JSON.stringify(baseUrl)};
$token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};
$client = new Client(['base_uri' => rtrim($baseUrl, '/') . '/', 'timeout' => 30]);
try {
    $response = $client->get('api/external/inventory-by-branch', [
        'query' => [
            'partner_company' => 'ALDO-2026-001',
            'branch_id' => 1,
        ],
        'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $token],
    ]);
    echo 'HTTP ' . $response->getStatusCode() . PHP_EOL;
    echo (string) $response->getBody() . PHP_EOL;
} catch (RequestException $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}`,
            },
            orders: {
                curl: (baseUrl, token) => `# === POST /api/external/orders (cuerpo JSON, mismos nombres que Laravel) ===
# Obligatorios:
#   client_id (int, existe en clients)
#   items[] (min 1 linea)
# Por linea en items[] obligatorios:
#   product_id (int), quantity (>0), unit_price (>=0)
# Opcionales (ejemplos abajo): branch_id, status (enum OrderStatus), convenio_type (enum), etc.
#
BASE="${baseUrl}"
TOKEN="${token}"

curl -sS -X POST "${baseUrl}/api/external/orders" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -H "Authorization: Bearer ${token}" \\
  -d '{
    "client_id": 1,
    "branch_id": null,
    "status": "pendiente",
    "convenio_type": "particular",
    "items": [
      {
        "product_id": 12,
        "quantity": 2,
        "unit_price": 12000,
        "discount_amount": 0,
        "inventory_id": null
      }
    ]
  }' \\
  -w "\\nHTTP %{http_code}\\n"`,
                javascript: (baseUrl, token) => `// === POST /api/external/orders ===
// Obligatorio: partner_company, client_id, items[] con product_id, quantity>0, unit_price>=0
// Opcional: branch_id, status (pendiente|en-proceso|finalizado), convenio_type (particular|eps|...), etc.

const baseUrl = ${JSON.stringify(baseUrl)};
const token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};

const payload = {
  partner_company: 'ALDO-2026-001',
  client_id: 1,
  branch_id: null,
  status: 'pendiente',
  convenio_type: 'particular',
  items: [
    {
      product_id: 12,
      quantity: 2,
      unit_price: 12000,
      discount_amount: 0,
    },
  ],
};

const response = await fetch(\`\${baseUrl}/api/external/orders\`, {
  method: 'POST',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: \`Bearer \${token}\`,
  },
  body: JSON.stringify(payload),
});

const bodyText = await response.text();
let data;
try {
  data = JSON.parse(bodyText);
} catch {
  data = bodyText;
}

console.log('HTTP', response.status);
console.log(data);`,
                python: (baseUrl, token) => `"""POST /api/external/orders
Obligatorio: partner_company, client_id, items[] (product_id, quantity>0, unit_price>=0).
Opcional: status, convenio_type, branch_id, ...
pip install requests
"""
import requests

BASE_URL = ${JSON.stringify(baseUrl)}
TOKEN = ${JSON.stringify(token || 'TU_TOKEN_AQUI')}

payload = {
    "partner_company": "ALDO-2026-001",
    "client_id": 1,
    "branch_id": None,
    "status": "pendiente",
    "convenio_type": "particular",
    "items": [
        {
            "product_id": 12,
            "quantity": 2,
            "unit_price": 12000,
            "discount_amount": 0,
        }
    ],
}

response = requests.post(
    f"{BASE_URL}/api/external/orders",
    json=payload,
    headers={
        "Accept": "application/json",
        "Authorization": f"Bearer {TOKEN}",
    },
    timeout=30,
)
print(response.status_code)
print(response.json())`,
                php: (baseUrl, token) => `&lt;?php
/**
 * POST /api/external/orders
 * Obligatorio: partner_company, client_id, items[] (product_id, quantity, unit_price)
 */

$baseUrl = ${JSON.stringify(baseUrl)};
$token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};

$payload = [
    'partner_company' => 'ALDO-2026-001',
    'client_id' => 1,
    'branch_id' => null,
    'status' => 'pendiente',
    'convenio_type' => 'particular',
    'items' => [
        [
            'product_id' => 12,
            'quantity' => 2,
            'unit_price' => 12000,
            'discount_amount' => 0,
        ],
    ],
];

$url = rtrim($baseUrl, '/') . '/api/external/orders';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

echo 'HTTP ' . $status . PHP_EOL;
echo $body . PHP_EOL;`,
                guzzle: (baseUrl, token) => `&lt;?php
/**
 * POST /api/external/orders — Guzzle
 * composer require guzzlehttp/guzzle:^7
 */

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\\Client;
use GuzzleHttp\\Exception\\RequestException;

$baseUrl = ${JSON.stringify(baseUrl)};
$token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};

$client = new Client([
    'base_uri' => rtrim($baseUrl, '/') . '/',
    'timeout' => 30,
]);

$payload = [
    'partner_company' => 'ALDO-2026-001',
    'client_id' => 1,
    'branch_id' => null,
    'status' => 'pendiente',
    'convenio_type' => 'particular',
    'items' => [
        [
            'product_id' => 12,
            'quantity' => 2,
            'unit_price' => 12000,
            'discount_amount' => 0,
        ],
    ],
];

try {
    $response = $client->post('api/external/orders', [
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ],
        'json' => $payload,
    ]);

    echo 'HTTP ' . $response->getStatusCode() . PHP_EOL;
    echo (string) $response->getBody() . PHP_EOL;
} catch (RequestException $e) {
    $res = $e->hasResponse() ? $e->getResponse() : null;
    echo $e->getMessage() . PHP_EOL;
    if ($res) {
        echo (string) $res->getBody() . PHP_EOL;
    }
    exit(1);
}`,
            },
            service_orders: {
                curl: (baseUrl, token) => `# === POST /api/external/service-orders (parametros reales, JSON) ===
# Obligatorios en raiz:
#   partner_company     string   codigo en partner_companies.code
#   status              string
#   priority            string
#   service_type        string
#   external_reference  string
#   patient_name, patient_document, patient_email  string
#   patient_phone       string solo digitos
#   diagnosis           string
#   items[]             min 1 objeto con: name (string), indicacion (string min 1)
# NO enviar ordered_at (lo asigna el servidor)
#
BASE="${baseUrl}"
TOKEN="${token}"

curl -sS -X POST "${baseUrl}/api/external/service-orders" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -H "Authorization: Bearer ${token}" \\
  -d '{"partner_company":"ALDO-2026-001","status":"en-proceso","priority":"media","service_type":"consulta","external_reference":"EXT-REF-001","patient_name":"Maria Gomez","patient_document":"1234567890","patient_phone":"3001234567","patient_email":"maria@example.com","diagnosis":"Control","items":[{"name":"Paracetamol 500 mg","indicacion":"1 tableta cada 8 horas"}]}' \\
  -w "\\nHTTP %{http_code}\\n"`,
                javascript: (baseUrl, token) => `// === POST /api/external/service-orders ===
// JSON con los mismos campos que StoreExternalServiceOrderRequest.
// partner_company = code del aliado. ordered_at no se envia.

const baseUrl = ${JSON.stringify(baseUrl)};
const token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};

const payload = {
  partner_company: 'ALDO-2026-001',
  status: 'en-proceso',
  priority: 'media',
  service_type: 'consulta',
  external_reference: 'EXT-REF-001',
  patient_name: 'Maria Gomez',
  patient_document: '1234567890',
  patient_phone: '3001234567',
  patient_email: 'maria@example.com',
  diagnosis: 'Control',
  items: [
    { name: 'Paracetamol 500 mg', indicacion: '1 tableta cada 8 horas' },
  ],
};

const response = await fetch(\`\${baseUrl}/api/external/service-orders\`, {
  method: 'POST',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: \`Bearer \${token}\`,
  },
  body: JSON.stringify(payload),
});

const bodyText = await response.text();
let data;
try {
  data = JSON.parse(bodyText);
} catch {
  data = bodyText;
}

console.log('HTTP', response.status);
console.log(data);`,
                python: (baseUrl, token) => `"""POST /api/external/service-orders
Campos obligatorios: partner_company, status, priority, service_type, external_reference,
patient_*, diagnosis, items[{name, indicacion}].
pip install requests
"""
import requests

BASE_URL = ${JSON.stringify(baseUrl)}
TOKEN = ${JSON.stringify(token || 'TU_TOKEN_AQUI')}

payload = {
    "partner_company": "ALDO-2026-001",
    "status": "en-proceso",
    "priority": "media",
    "service_type": "consulta",
    "external_reference": "EXT-REF-001",
    "patient_name": "Maria Gomez",
    "patient_document": "1234567890",
    "patient_phone": "3001234567",
    "patient_email": "maria@example.com",
    "diagnosis": "Control",
    "items": [
        {"name": "Paracetamol 500 mg", "indicacion": "1 tableta cada 8 horas"},
    ],
}

response = requests.post(
    f"{BASE_URL}/api/external/service-orders",
    json=payload,
    headers={
        "Accept": "application/json",
        "Authorization": f"Bearer {TOKEN}",
    },
    timeout=30,
)
print(response.status_code)
print(response.json())`,
                php: (baseUrl, token) => `&lt;?php
/**
 * POST /api/external/service-orders
 * Body: partner_company (code), paciente, diagnosis, items[name+indicacion]
 */

$baseUrl = ${JSON.stringify(baseUrl)};
$token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};

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
            'indicacion' => '1 tableta cada 8 horas',
        ],
    ],
];

$url = rtrim($baseUrl, '/') . '/api/external/service-orders';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

echo 'HTTP ' . $status . PHP_EOL;
echo $body . PHP_EOL;`,
                guzzle: (baseUrl, token) => `&lt;?php
/**
 * POST /api/external/service-orders — Guzzle
 * composer require guzzlehttp/guzzle:^7
 */

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\\Client;
use GuzzleHttp\\Exception\\RequestException;

$baseUrl = ${JSON.stringify(baseUrl)};
$token = ${JSON.stringify(token || 'TU_TOKEN_AQUI')};

$client = new Client([
    'base_uri' => rtrim($baseUrl, '/') . '/',
    'timeout' => 30,
]);

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
            'indicacion' => '1 tableta cada 8 horas',
        ],
    ],
];

try {
    $response = $client->post('api/external/service-orders', [
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ],
        'json' => $payload,
    ]);

    echo 'HTTP ' . $response->getStatusCode() . PHP_EOL;
    echo (string) $response->getBody() . PHP_EOL;
} catch (RequestException $e) {
    $res = $e->hasResponse() ? $e->getResponse() : null;
    echo $e->getMessage() . PHP_EOL;
    if ($res) {
        echo (string) $res->getBody() . PHP_EOL;
    }
    exit(1);
}`,
            },
        };

        function refreshPlayground() {
            const endpoint = endpointSelect.value;
            const token = tokenInput.value.trim() || 'TU_TOKEN_AQUI';
            const baseUrl = playgroundBaseUrl();
            if (baseHint) {
                baseHint.textContent = 'URL base usada en los ejemplos: ' + baseUrl + ' (la misma de esta pagina).';
            }
            if (paramHint && endpointParamHints[endpoint]) {
                paramHint.innerHTML = endpointParamHints[endpoint];
            }
            codeBlock.textContent = snippets[endpoint][selectedLang](baseUrl, token);
        }

        langTabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                selectedLang = tab.getAttribute('data-lang');
                langTabs.forEach((item) => {
                    item.classList.remove('bg-zinc-900', 'text-white', 'dark:bg-white', 'dark:text-zinc-900');
                    item.classList.add('border', 'border-zinc-200', 'dark:border-white/10');
                });
                tab.classList.add('bg-zinc-900', 'text-white', 'dark:bg-white', 'dark:text-zinc-900');
                tab.classList.remove('border', 'border-zinc-200', 'dark:border-white/10');
                refreshPlayground();
            });
        });

        endpointSelect.addEventListener('change', refreshPlayground);
        tokenInput.addEventListener('input', refreshPlayground);
        copyPlayground.addEventListener('click', () => {
            navigator.clipboard.writeText(codeBlock.textContent.trim());
            const oldText = copyPlayground.textContent;
            copyPlayground.textContent = 'Copiado';
            setTimeout(() => copyPlayground.textContent = oldText, 1400);
        });

        refreshPlayground();
    </script>
</body>
</html>
