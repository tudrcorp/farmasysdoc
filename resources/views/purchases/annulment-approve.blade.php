<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c0c0f">
    <title>Anulación de compra · Farmadoc</title>
    <style>
        :root {
            --bg0: #0c0c0f;
            --card: rgba(255, 255, 255, 0.09);
            --card-border: rgba(255, 255, 255, 0.14);
            --text: #f4f4f5;
            --muted: rgba(244, 244, 245, 0.62);
            --accent: #18acb2;
            --danger: #f87171;
            --radius: 20px;
            --radius-sm: 14px;
            --tap: 48px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100dvh;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display", "Segoe UI", sans-serif;
            background:
                radial-gradient(100% 60% at 50% -5%, rgba(24, 172, 178, 0.22), transparent 50%),
                radial-gradient(80% 50% at 100% 100%, rgba(252, 228, 34, 0.08), transparent 45%),
                var(--bg0);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            padding: max(16px, env(safe-area-inset-top)) max(18px, env(safe-area-inset-right)) max(28px, env(safe-area-inset-bottom)) max(18px, env(safe-area-inset-left));
        }
        .wrap {
            max-width: 520px;
            margin: 0 auto;
        }
        @media (min-width: 768px) {
            .wrap { max-width: 720px; }
            .summary-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0 20px;
            }
        }
        h1 {
            font-size: clamp(1.25rem, 4vw, 1.45rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            margin: 0 0 8px;
            line-height: 1.15;
        }
        .sub {
            color: var(--muted);
            font-size: 0.94rem;
            margin-bottom: 22px;
            line-height: 1.5;
        }
        .glass {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            backdrop-filter: blur(28px) saturate(1.5);
            -webkit-backdrop-filter: blur(28px) saturate(1.5);
            padding: 18px 20px;
            margin-bottom: 16px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
        }
        .row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
            font-size: 0.93rem;
        }
        .row:last-child { border-bottom: 0; }
        .k { color: var(--muted); flex-shrink: 0; max-width: 42%; }
        .v { text-align: right; font-weight: 600; word-break: break-word; }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(251, 191, 36, 0.14);
            color: #fde68a;
            border: 1px solid rgba(251, 191, 36, 0.35);
            margin-bottom: 16px;
        }
        .lines {
            max-height: min(42vh, 280px);
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 10px;
            padding-right: 4px;
        }
        .line {
            font-size: 0.86rem;
            color: var(--muted);
            padding: 6px 0;
            line-height: 1.35;
        }
        .field-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }
        select.status-select {
            width: 100%;
            min-height: var(--tap);
            border-radius: var(--radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(0, 0, 0, 0.35);
            color: var(--text);
            font-size: 1.02rem;
            font-weight: 600;
            padding: 12px 14px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23a1a1aa'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 1.25rem;
        }
        select.status-select:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(24, 172, 178, 0.45);
            border-color: rgba(24, 172, 178, 0.55);
        }
        .hint-block {
            font-size: 0.84rem;
            color: var(--muted);
            line-height: 1.45;
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .hint-block strong { color: #fecaca; }
        form { margin-top: 4px; }
        button[type="submit"] {
            width: 100%;
            min-height: var(--tap);
            border: 0;
            border-radius: var(--radius-sm);
            margin-top: 18px;
            font-size: 1.02rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: #0c0c0f;
            background: linear-gradient(135deg, #fce422, #e8cc00);
            box-shadow: 0 14px 36px rgba(252, 228, 34, 0.28);
            cursor: pointer;
        }
        button[type="submit"]:active { transform: scale(0.985); }
        .footer-hint {
            font-size: 0.76rem;
            color: var(--muted);
            margin-top: 16px;
            text-align: center;
            line-height: 1.4;
        }
        .errors {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fecaca;
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            font-size: 0.88rem;
            margin-bottom: 14px;
        }
        .errors ul { margin: 0; padding-left: 1.1rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="pill">Solicita anulación</div>
        <h1>Confirmar anulación</h1>
        <p class="sub">Revise el resumen. Al guardar con estado «Anulada» se revertirá el inventario (con registro en <strong>Ajustes de inventario</strong>), se eliminará el histórico de compra de contado si aplica y se marcará la cuenta por pagar como anulada si la compra fue a crédito.</p>

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="glass summary-grid">
            <div class="row"><span class="k">Orden</span><span class="v">{{ $purchase->purchase_number }}</span></div>
            <div class="row"><span class="k">Proveedor</span><span class="v">{{ $purchase->supplier?->displayName() ?? '—' }}</span></div>
            <div class="row"><span class="k">Sucursal</span><span class="v">{{ $purchase->branch?->name ?? '—' }}</span></div>
            <div class="row"><span class="k">Total</span><span class="v">{{ number_format((float) ($purchase->total ?? 0), 2, ',', '.') }}</span></div>
            <div class="row"><span class="k">Factura prov.</span><span class="v">{{ $purchase->supplier_invoice_number ?: '—' }}</span></div>
            <div class="row"><span class="k">Pago</span><span class="v">{{ \App\Support\Purchases\PurchasePaymentStatus::label($purchase->payment_status) }}</span></div>
        </div>

        @if ($purchase->items->isNotEmpty())
            <div class="glass">
                <strong style="font-size:0.92rem; letter-spacing:-0.02em;">Líneas</strong>
                <div class="lines">
                    @foreach ($purchase->items as $line)
                        <div class="line">
                            #{{ $line->line_number }}
                            · {{ $line->product_name_snapshot ?: 'Producto' }}
                            · {{ number_format((float) $line->quantity_ordered, 3, ',', '.') }} u
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="glass">
            <label class="field-label" for="purchase_status">Estado de la orden</label>
            <form method="post" action="{{ $confirmUrl }}" id="annul-form">
                @csrf
                <select class="status-select" id="purchase_status" name="purchase_status" required>
                    <option value="" disabled @if(old('purchase_status', '') === '') selected @endif>Seleccione…</option>
                    <option value="{{ $annulledStatusValue }}" @if(old('purchase_status') === $annulledStatusValue) selected @endif>{{ $annulledStatusLabel }}</option>
                </select>
                <p class="hint-block">Solo use <strong>Anulada</strong> cuando corresponda ejecutar la reversión. Esta acción no se puede deshacer desde esta pantalla.</p>
                <button type="submit">Guardar</button>
            </form>
        </div>

        <p class="footer-hint">Solo administradores autenticados. Enlace firmado; caduca en breve. No lo reenvíe a terceros.</p>
    </div>
</body>
</html>
