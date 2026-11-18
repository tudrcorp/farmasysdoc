<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ filled($title ?? null) ? $title.' · Farmadoc' : 'Pedido aliado · Farmadoc' }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon.png') }}">
        @livewireStyles
        <style>
            :root {
                --ink: #10292d;
                --muted: #5d7176;
                --line: #d7e4e6;
                --paper: #f3f7f7;
                --card: #ffffff;
                --brand: #0e3a3f;
                --accent: #0e949a;
                --accent-dark: #0b757a;
                --danger: #9f1239;
                --ok: #0f766e;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: "Segoe UI", ui-sans-serif, system-ui, sans-serif;
                color: var(--ink);
                background:
                    radial-gradient(900px 420px at 100% -10%, rgba(14, 148, 154, 0.18), transparent 60%),
                    var(--paper);
            }
            .pg-header {
                background: var(--brand);
                color: #fff;
            }
            .pg-header-inner, .pg-main {
                width: min(1040px, calc(100% - 32px));
                margin: 0 auto;
            }
            .pg-header-inner {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 18px 0;
            }
            .pg-logo { height: 42px; width: auto; display: block; }
            .pg-kicker { margin: 0; font-size: 12px; letter-spacing: 0.14em; text-transform: uppercase; color: #b7e4e6; }
            .pg-main { padding: 28px 0 64px; }
            .pg-card {
                background: var(--card);
                border: 1px solid var(--line);
                border-radius: 20px;
                padding: 22px;
                box-shadow: 0 18px 40px -28px rgba(16, 41, 45, 0.45);
            }
            .pg-card + .pg-card { margin-top: 16px; }
            h1 { font-size: 28px; line-height: 1.15; margin: 0 0 8px; }
            h2 { font-size: 18px; margin: 0 0 6px; }
            p { line-height: 1.5; }
            .pg-muted { color: var(--muted); }
            label { display: block; font-size: 13px; font-weight: 650; margin-bottom: 6px; }
            input, select, textarea {
                width: 100%;
                border: 1px solid #c5d5d8;
                border-radius: 12px;
                padding: 12px 14px;
                font: inherit;
                color: var(--ink);
                background: #fff;
            }
            input:focus, select:focus, textarea:focus {
                outline: 3px solid rgba(14, 148, 154, 0.25);
                border-color: var(--accent);
            }
            .pg-btn, .pg-btn-ghost, .pg-link {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                border-radius: 999px;
                padding: 12px 18px;
                font-weight: 700;
                text-decoration: none;
                border: 0;
                cursor: pointer;
            }
            .pg-btn { background: var(--accent); color: #fff; }
            .pg-btn:hover { background: var(--accent-dark); }
            .pg-btn-ghost { background: #e7f4f5; color: var(--brand); }
            .pg-link { background: #fff; color: var(--brand); border: 1px solid var(--line); }
            .pg-error { color: var(--danger); font-size: 13px; margin: 6px 0 0; }
            .pg-grid { display: grid; gap: 12px; }
            .pg-grid-2 { grid-template-columns: 1fr; }
            .pg-steps { display: flex; gap: 8px; margin: 0 0 18px; padding: 0; list-style: none; }
            .pg-steps li {
                flex: 1;
                text-align: center;
                font-size: 12px;
                font-weight: 700;
                color: var(--muted);
                padding-bottom: 8px;
                border-bottom: 3px solid #d5e3e4;
            }
            .pg-steps li.is-on { color: var(--brand); border-color: var(--accent); }
            .pg-results, .pg-lines { display: grid; gap: 8px; }
            .pg-hit, .pg-line {
                display: grid;
                gap: 8px;
                align-items: center;
                border: 1px solid var(--line);
                border-radius: 14px;
                padding: 12px;
                background: #fff;
            }
            .pg-hit { grid-template-columns: 1fr auto; }
            .pg-line { grid-template-columns: 1.4fr 0.8fr 0.9fr 90px auto; }
            .pg-name { font-weight: 700; }
            .pg-meta { color: var(--muted); font-size: 13px; }
            .pg-qty { width: 90px; }
            .pg-summary {
                display: flex;
                justify-content: space-between;
                gap: 16px;
                align-items: center;
            }
            .pg-total { font-size: 28px; font-weight: 800; margin: 0; }
            .pg-check {
                width: 56px; height: 56px; border-radius: 50%;
                display: grid; place-items: center;
                background: #d1fae5; color: var(--ok); font-size: 28px; font-weight: 800;
            }
            .pg-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
            @media (min-width: 760px) {
                .pg-grid-2 { grid-template-columns: 1fr 1fr; }
            }
            @media (max-width: 759px) {
                .pg-line { grid-template-columns: 1fr auto; }
                .pg-summary { flex-direction: column; align-items: stretch; }
            }
        </style>
    </head>
    <body>
        <header class="pg-header">
            <div class="pg-header-inner">
                <img class="pg-logo" src="{{ asset('images/logos/farmadoc-ligth.png') }}" alt="Farmadoc">
                <p class="pg-kicker">Pedidos para aliados</p>
            </div>
        </header>
        <main class="pg-main">
            {{ $slot }}
        </main>
        @livewireScripts
    </body>
</html>
