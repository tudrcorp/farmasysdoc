<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ filled($title ?? null) ? $title.' · Farmadoc' : 'Pedido aliado · Farmadoc' }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon.png') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet">
        @livewireStyles
        <style>
            :root {
                --ink: #102226;
                --muted: #5c7276;
                --line: #e3ecee;
                --paper: #f4f8f8;
                --card: #ffffff;
                --brand: #0c3338;
                --accent: #0e949a;
                --accent-dark: #0a7479;
                --soft: #e7f6f6;
                --danger: #be123c;
                --ok: #0f766e;
                --shadow: 0 24px 50px -32px rgba(12, 51, 56, 0.45);
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif;
                color: var(--ink);
                background:
                    radial-gradient(720px 280px at 0% -10%, rgba(14, 148, 154, 0.16), transparent 70%),
                    var(--paper);
            }
            button, input, select, textarea { font: inherit; }
            .pg-header {
                position: sticky;
                top: 0;
                z-index: 20;
                background: rgba(12, 51, 56, 0.94);
                backdrop-filter: blur(16px);
                color: #fff;
            }
            .pg-header-inner, .pg-main { width: min(1180px, calc(100% - 32px)); margin: 0 auto; }
            .pg-header-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 72px; }
            .pg-logo { height: 36px; width: auto; display: block; }
            .pg-kicker { margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: #9edfe2; }
            .pg-main { padding: 28px 0 108px; }
            .pg-card {
                background: var(--card);
                border: 1px solid var(--line);
                border-radius: 24px;
                padding: 22px;
                box-shadow: var(--shadow);
            }
            .pg-card + .pg-card, .pg-stack > * + * { margin-top: 16px; }
            h1 { font-size: clamp(28px, 4vw, 40px); line-height: 1.08; letter-spacing: -0.03em; margin: 0 0 8px; }
            h2 { font-size: 16px; margin: 0; letter-spacing: -0.02em; }
            p { line-height: 1.55; }
            .pg-muted { color: var(--muted); }
            label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #3d5559; }
            input, select, textarea {
                width: 100%;
                border: 1px solid #d5e2e4;
                border-radius: 14px;
                padding: 13px 14px;
                color: var(--ink);
                background: #fbfefe;
            }
            input:focus, select:focus, textarea:focus { outline: 3px solid rgba(14, 148, 154, 0.22); border-color: var(--accent); background: #fff; }
            .pg-btn, .pg-btn-ghost, .pg-link {
                display: inline-flex; align-items: center; justify-content: center; gap: 8px;
                border-radius: 999px; padding: 12px 18px; font-weight: 700; text-decoration: none; border: 0; cursor: pointer;
            }
            .pg-btn { background: var(--accent); color: #fff; }
            .pg-btn:hover { background: var(--accent-dark); }
            .pg-btn:disabled { opacity: 0.55; cursor: wait; }
            .pg-btn-ghost { background: var(--soft); color: var(--brand); }
            .pg-link { background: #fff; color: var(--brand); border: 1px solid var(--line); }
            .pg-error { color: var(--danger); font-size: 13px; margin: 6px 0 0; }
            .pg-grid { display: grid; gap: 12px; }
            .pg-grid-2 { grid-template-columns: 1fr; }
            .pg-steps { display: flex; gap: 8px; margin: 0 0 22px; padding: 0; list-style: none; }
            .pg-steps li { flex: 1; border-radius: 999px; background: #fff; border: 1px solid var(--line); color: var(--muted); text-align: center; font-size: 13px; font-weight: 700; padding: 10px 8px; }
            .pg-steps li.is-on { background: var(--brand); color: #fff; border-color: var(--brand); }
            .pg-steps li.is-done { background: var(--soft); color: var(--brand); border-color: transparent; }
            .pg-gate { width: min(520px, 100%); margin: 24px auto 0; }
            .pg-search { position: relative; }
            .pg-search svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); }
            .pg-search input { padding-left: 42px; border-radius: 16px; min-height: 52px; }
            .pg-shop { display: grid; gap: 18px; align-items: start; }
            .pg-catalog, .pg-ticket { min-width: 0; }
            .pg-ticket { position: sticky; top: 90px; display: flex; flex-direction: column; padding-bottom: 16px; overflow: hidden; }
            .pg-ticket-scroll { flex: 1 1 auto; min-height: 0; overflow: auto; padding-right: 2px; }
            .pg-ticket-foot { flex: 0 0 auto; background: #fff; padding-top: 8px; }
            .pg-results { display: grid; gap: 8px; margin-top: 14px; }
            .pg-hit {
                display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: center;
                padding: 12px 12px 12px 14px; border-radius: 16px; background: #fff; border: 1px solid var(--line);
            }
            .pg-hit:hover { border-color: #b7dbde; }
            .pg-name { font-weight: 700; letter-spacing: -0.02em; }
            .pg-meta { color: var(--muted); font-size: 13px; margin-top: 2px; }
            .pg-price { font-weight: 800; font-variant-numeric: tabular-nums; }
            .pg-add {
                height: 40px; padding: 0 14px; border: 0; border-radius: 999px; background: var(--brand); color: #fff; font-weight: 700; cursor: pointer;
            }
            .pg-add:hover { background: #16484e; }
            .pg-stepper { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: 999px; background: #fff; overflow: hidden; }
            .pg-stepper button { width: 34px; height: 34px; border: 0; background: transparent; font-size: 18px; cursor: pointer; color: var(--brand); }
            .pg-stepper button:hover { background: var(--soft); }
            .pg-stepper span { min-width: 22px; text-align: center; font-weight: 800; font-variant-numeric: tabular-nums; }
            .pg-line { display: grid; grid-template-columns: 1fr auto; gap: 10px; padding: 12px 0; border-bottom: 1px solid var(--line); }
            .pg-line:last-child { border-bottom: 0; }
            .pg-choices { display: flex; flex-wrap: wrap; gap: 8px; }
            .pg-choice {
                border: 1px solid var(--line); background: #fff; color: var(--ink); border-radius: 14px; padding: 10px 12px; font-weight: 700; cursor: pointer;
            }
            .pg-choice.is-on { background: var(--brand); color: #fff; border-color: var(--brand); }
            .pg-section { margin-top: 18px; }
            .pg-totals { display: grid; gap: 6px; margin: 16px 0; font-size: 14px; }
            .pg-totals div { display: flex; justify-content: space-between; color: var(--muted); }
            .pg-totals .is-grand { color: var(--ink); font-size: 22px; font-weight: 800; letter-spacing: -0.03em; }
            .pg-totals .is-grand span:last-child { font-variant-numeric: tabular-nums; }
            .pg-btn-block { width: 100%; min-height: 50px; }
            .pg-empty { padding: 22px 8px; text-align: center; color: var(--muted); }
            .pg-qr { width: min(180px, 100%); border-radius: 16px; border: 1px solid var(--line); background: #fff; }
            .pg-check { width: 64px; height: 64px; border-radius: 22px; display: grid; place-items: center; background: #d1fae5; color: var(--ok); font-size: 30px; font-weight: 800; }
            .pg-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
            .pg-dock {
                display: none; position: fixed; left: 16px; right: 16px; bottom: 16px; z-index: 30;
                background: var(--brand); color: #fff; border-radius: 18px; padding: 12px 14px;
                align-items: center; justify-content: space-between; text-decoration: none; box-shadow: var(--shadow);
            }
            .pg-dock strong { font-size: 16px; }
            @media (min-width: 760px) { .pg-grid-2 { grid-template-columns: 1fr 1fr; } }
            @media (min-width: 980px) {
                .pg-shop { grid-template-columns: minmax(0, 1.35fr) minmax(340px, 0.85fr); }
                .pg-ticket { height: calc(100vh - 196px); max-height: calc(100vh - 196px); }
                .pg-ticket-foot { position: sticky; bottom: 0; border-top: 1px solid var(--line); }
            }
            @media (max-width: 979px) {
                .pg-ticket { position: static; }
                .pg-dock { display: flex; }
            }
            @media (max-width: 640px) {
                .pg-hit { grid-template-columns: 1fr; }
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
