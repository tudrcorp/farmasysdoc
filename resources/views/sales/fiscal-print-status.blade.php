<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->type->label() }} fiscal — {{ $sale->sale_number }}</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            margin: 0;
            padding: 1rem;
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: #18181b;
            background: #f4f4f5;
        }

        .card {
            max-width: 32rem;
            margin: 2rem auto;
            padding: 1.5rem;
            background: #fff;
            border: 1px solid #e4e4e7;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
            text-align: center;
        }

        h1 {
            margin: 0 0 0.25rem;
            font-size: 1.125rem;
        }

        .muted {
            color: #52525b;
            font-size: 0.875rem;
        }

        .state {
            margin: 1.5rem 0;
            padding: 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
        }

        .state--waiting { background: #f4f4f5; color: #3f3f46; }
        .state--ok { background: #ecfdf5; color: #065f46; }
        .state--warn { background: #fffbeb; color: #92400e; }
        .state--error { background: #fef2f2; color: #991b1b; }

        .fiscal-number {
            display: block;
            margin-top: 0.5rem;
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 1.5rem;
        }

        .detail {
            margin-top: 0.5rem;
            font-weight: 400;
            font-size: 0.8125rem;
            white-space: pre-wrap;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }

        .actions a,
        .actions button {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 0.875rem;
            border-radius: 0.375rem;
            font: inherit;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid #d4d4d8;
            background: #f4f4f5;
            color: #18181b;
        }

        .actions .primary {
            background: #18181b;
            border-color: #18181b;
            color: #fafafa;
        }

        .continue {
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px dashed #d4d4d8;
        }

        .continue a {
            display: inline-flex;
            margin-top: 0.5rem;
            padding: 0.6rem 1rem;
            border-radius: 0.375rem;
            background: #18181b;
            color: #fafafa;
            font-weight: 600;
            text-decoration: none;
        }

        [hidden] {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $document->type->label() }} · {{ $sale->sale_number }}</h1>
        <div class="muted">Máquina fiscal: <strong id="fp-printer">{{ $initialState['printer'] ?? '—' }}</strong></div>

        @if ($errors->any())
            <div class="state state--error">{{ $errors->first() }}</div>
        @endif

        <div id="fp-state" class="state state--waiting" role="status" aria-live="polite">
            <span id="fp-label">{{ $initialState['label'] }}</span>
            <span id="fp-number" class="fiscal-number" hidden></span>
            <div id="fp-detail" class="detail" hidden></div>
        </div>

        <div class="actions">
            <a href="{{ $salesIndexUrl }}" class="primary" id="fp-back">Volver a caja</a>
            <a href="{{ $saleViewUrl }}">Ver venta</a>
            <form method="POST" action="{{ $retryUrl }}" id="fp-retry" hidden>
                @csrf
                <button type="submit">Reintentar impresión</button>
            </form>
            <a href="{{ $nonFiscalUrl }}" id="fp-non-fiscal" hidden>Comprobante no fiscal</a>
        </div>

        <div id="fp-continue" class="continue" hidden>
            <p class="muted">La máquina fiscal está tardando. La factura quedó en cola y saldrá sola cuando la máquina responda; puede seguir atendiendo.</p>
            <a href="{{ $salesIndexUrl }}" class="primary">Continuar con la siguiente venta</a>
        </div>
    </div>

    <script>
        (function () {
            var statusUrl = @js($statusUrl);
            var stateBox = document.getElementById('fp-state');
            var label = document.getElementById('fp-label');
            var number = document.getElementById('fp-number');
            var detail = document.getElementById('fp-detail');
            var retry = document.getElementById('fp-retry');
            var nonFiscal = document.getElementById('fp-non-fiscal');
            var continueBox = document.getElementById('fp-continue');
            var continueAfterMs = @js($continueAfterSeconds) * 1000;
            var startedAt = Date.now();

            function render(state) {
                var variant = 'state--waiting';
                if (state.status === 'impreso') {
                    variant = 'state--ok';
                } else if (state.status === 'fallido') {
                    variant = 'state--error';
                } else if (state.status === 'requiere_revision') {
                    variant = 'state--warn';
                }

                stateBox.className = 'state ' + variant;
                label.textContent = state.label;

                number.hidden = ! state.fiscal_number;
                number.textContent = state.fiscal_number ? 'Nº ' + state.fiscal_number : '';

                var message = state.error || '';
                if (! state.is_final && ! state.printer_online && Date.now() - startedAt > 5000) {
                    message = 'El agente de la máquina fiscal no responde. Verifique que la PC de caja esté encendida y conectada.';
                }
                if (state.status === 'requiere_revision') {
                    message = (message ? message + '\n' : '') + 'No reimprima: un supervisor debe verificar la máquina fiscal en «Documentos fiscales».';
                }
                detail.hidden = message === '';
                detail.textContent = message;

                continueBox.hidden = state.is_final || Date.now() - startedAt < continueAfterMs;

                retry.hidden = ! state.can_retry;
                nonFiscal.hidden = state.status !== 'fallido';
            }

            function poll() {
                fetch(statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (state) {
                        if (! state) {
                            setTimeout(poll, 3000);
                            return;
                        }
                        render(state);
                        if (! state.is_final) {
                            setTimeout(poll, 1000);
                        }
                    })
                    .catch(function () { setTimeout(poll, 3000); });
            }

            render(@js($initialState));
            if (! @js($initialState['is_final'])) {
                setTimeout(poll, 1000);
            }
        })();
    </script>
</body>
</html>
