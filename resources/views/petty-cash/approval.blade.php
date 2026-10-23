<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c0c0f">
    <title>Caja chica · Farmadoc</title>
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
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
            background:
                radial-gradient(100% 60% at 50% -5%, rgba(24, 172, 178, 0.22), transparent 50%),
                radial-gradient(80% 50% at 100% 100%, rgba(252, 228, 34, 0.08), transparent 45%),
                var(--bg0);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            padding: max(16px, env(safe-area-inset-top)) max(18px, env(safe-area-inset-right)) max(28px, env(safe-area-inset-bottom)) max(18px, env(safe-area-inset-left));
        }
        .wrap { max-width: 520px; margin: 0 auto; }
        @media (min-width: 768px) {
            .wrap { max-width: 720px; }
            .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
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
        .pay-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 12px;
            margin-bottom: 4px;
        }
        .pay-hint { font-size: 0.8rem; color: var(--muted); margin: 0 0 8px; line-height: 1.4; }
        .pay-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
            font-size: 0.93rem;
        }
        .pay-row:last-child { border-bottom: 0; }
        .pay-row .k { max-width: 36%; }
        .pay-row .v { flex: 1; text-align: right; }
        .copy-btn {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            padding: 0;
            border-radius: 12px;
            border: 1px solid rgba(24, 172, 178, 0.45);
            background: rgba(24, 172, 178, 0.16);
            color: #67e8f9;
            cursor: pointer;
        }
        .copy-btn svg { width: 20px; height: 20px; display: block; }
        .copy-btn .icon-check { display: none; }
        .copy-btn:active { transform: scale(0.97); }
        .copy-btn.copied {
            border-color: rgba(34, 197, 94, 0.45);
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }
        .copy-btn.copied .icon-copy { display: none; }
        .copy-btn.copied .icon-check { display: block; }
        .notes { font-size: 0.92rem; line-height: 1.45; color: var(--text); }
        .pill {
            display: inline-flex;
            align-items: center;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(251, 191, 36, 0.14);
            color: #fde68a;
            border: 1px solid rgba(251, 191, 36, 0.35);
            margin-bottom: 16px;
        }
        .pill.ok {
            background: rgba(34, 197, 94, 0.14);
            color: #86efac;
            border-color: rgba(34, 197, 94, 0.35);
        }
        .pill.no {
            background: rgba(239, 68, 68, 0.14);
            color: #fecaca;
            border-color: rgba(239, 68, 68, 0.35);
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
        input[type="text"], textarea, input[type="file"] {
            width: 100%;
            border-radius: var(--radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(0, 0, 0, 0.35);
            color: var(--text);
            font-size: 1rem;
            padding: 12px 14px;
        }
        input[type="text"], textarea { min-height: var(--tap); }
        textarea { min-height: 88px; resize: vertical; }
        input:focus, textarea:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(24, 172, 178, 0.45);
            border-color: rgba(24, 172, 178, 0.55);
        }
        .hint { font-size: 0.82rem; color: var(--muted); margin: 8px 0 0; line-height: 1.4; }
        .file-upload { position: relative; }
        .file-upload__input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .file-upload__surface {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            min-height: 88px;
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            border: 1px dashed rgba(255, 255, 255, 0.22);
            background: rgba(0, 0, 0, 0.35);
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        .file-upload__surface:hover,
        .file-upload__surface:focus-visible {
            outline: none;
            border-color: rgba(24, 172, 178, 0.55);
            background: rgba(24, 172, 178, 0.08);
            box-shadow: 0 0 0 3px rgba(24, 172, 178, 0.25);
        }
        .file-upload__placeholder {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
        }
        .file-upload__icon {
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: rgba(24, 172, 178, 0.16);
            border: 1px solid rgba(24, 172, 178, 0.35);
            color: #67e8f9;
        }
        .file-upload__icon svg { width: 26px; height: 26px; }
        .file-upload__placeholder-text strong {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }
        .file-upload__placeholder-text span {
            font-size: 0.82rem;
            color: var(--muted);
            line-height: 1.35;
        }
        .file-upload__selected {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
        }
        .file-upload__selected[hidden],
        .file-upload__placeholder[hidden] { display: none !important; }
        .file-upload__thumb-wrap {
            flex-shrink: 0;
            width: 72px;
            height: 72px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(0, 0, 0, 0.45);
            display: grid;
            place-items: center;
        }
        .file-upload__thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .file-upload__pdf {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: #fecaca;
        }
        .file-upload__meta {
            flex: 1;
            min-width: 0;
        }
        .file-upload__name {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            word-break: break-word;
            line-height: 1.35;
            margin-bottom: 8px;
        }
        .file-upload__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .file-upload__btn {
            font: inherit;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.06);
            color: var(--text);
            cursor: pointer;
        }
        .file-upload__btn--ghost {
            color: var(--muted);
        }
        .file-upload__btn:active { transform: scale(0.98); }
        button[type="submit"] {
            width: 100%;
            min-height: var(--tap);
            border: 0;
            border-radius: var(--radius-sm);
            margin-top: 16px;
            font-size: 1.02rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            cursor: pointer;
        }
        .btn-progress {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
            cursor: not-allowed;
            --progress: 0%;
        }
        .btn-progress:not(:disabled) {
            cursor: pointer;
            box-shadow: 0 14px 36px rgba(252, 228, 34, 0.22);
        }
        .btn-progress:disabled:active { transform: none; }
        .btn-progress__fill {
            position: absolute;
            inset: 0 auto 0 0;
            width: var(--progress);
            z-index: 0;
            transition: width 0.22s ease;
            pointer-events: none;
        }
        .btn-ok .btn-progress__fill {
            background: linear-gradient(135deg, #fce422, #e8cc00);
        }
        .btn-no.btn-progress:not(:disabled) {
            box-shadow: 0 12px 32px rgba(239, 68, 68, 0.35);
        }
        .btn-no .btn-progress__fill {
            background: linear-gradient(135deg, #f87171, #dc2626);
        }
        .btn-progress__label {
            position: relative;
            z-index: 1;
            display: block;
            color: rgba(244, 244, 245, 0.92);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.45);
        }
        .btn-progress:not(:disabled) .btn-progress__label {
            color: #0c0c0f;
            text-shadow: none;
        }
        .btn-no:not(:disabled) .btn-progress__label {
            color: #fff;
        }
        .btn-ok:not(:disabled) {
            background: transparent;
        }
        .btn-no:not(:disabled) {
            background: transparent;
        }
        button[type="submit"]:not(:disabled):active { transform: scale(0.985); }
        .footer-hint {
            font-size: 0.76rem;
            color: var(--muted);
            margin-top: 16px;
            text-align: center;
            line-height: 1.4;
        }
        .footer-hint a { color: #67e8f9; }
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
        h2 { font-size: 1.02rem; margin: 0 0 12px; letter-spacing: -0.02em; }
    </style>
</head>
<body>
    <div class="wrap">
        @if ($isPending)
            <div class="pill">{{ $fund->withReplenishmentQualifier('Por aprobar') }}</div>
            <h1>{{ $fund->withReplenishmentQualifier('Solicitud de caja chica') }}</h1>
            <p class="sub">Revise el detalle. Si transfiere el fondo, adjunte la captura y la referencia. También puede resolverlo en Farmaadmin.</p>
        @else
            <div class="pill {{ $fund->status === \App\Enums\PettyCashFundStatus::Approved ? 'ok' : 'no' }}">{{ $fund->status->label() }}</div>
            <h1>Esta solicitud ya fue resuelta</h1>
            <p class="sub">No hay nada pendiente en este enlace. Puede ver el expediente en el panel.</p>
        @endif

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $isReplenishment = $fund->replenished_from_fund_id !== null;
        @endphp

        <div class="glass summary-grid">
            <div class="row"><span class="k">Sucursal</span><span class="v">{{ $fund->branch?->name ?? '—' }}</span></div>
            <div class="row"><span class="k">Solicitó</span><span class="v">{{ $fund->requestedBy?->name ?? '—' }}</span></div>
            <div class="row"><span class="k">Fecha</span><span class="v">{{ $fund->requested_at?->format('d/m/Y') ?? '—' }}</span></div>
            @if ($isReplenishment)
                <div class="row"><span class="k">Tipo</span><span class="v">Reposición (caja #{{ $fund->replenished_from_fund_id }})</span></div>
                @if ((float) $fund->carried_over_ves > 0.009)
                    <div class="row"><span class="k">Saldo anterior (sistema)</span><span class="v">{{ \App\Support\Finance\PettyCashMoney::formatVes((float) $fund->carried_over_ves) }}</span></div>
                @endif
                <div class="row"><span class="k">Total del fondo</span><span class="v">{{ \App\Support\Finance\PettyCashMoney::formatVes((float) $fund->amount_ves) }}</span></div>
            @endif
        </div>

        @php
            $accountTypeLabel = $fund->bank_account_type?->label();
            $paymentFields = [
                [
                    'label' => $isReplenishment ? 'Transferir (USD)' : 'Monto USD',
                    'display' => \App\Support\Finance\PettyCashMoney::formatUsd($fund->transferAmountUsd()),
                    'copy' => number_format($fund->transferAmountUsd(), 2, '.', ''),
                ],
                [
                    'label' => $isReplenishment ? 'Transferir (Bs.)' : 'Monto Bs.',
                    'display' => \App\Support\Finance\PettyCashMoney::formatVes($fund->transferAmountVes()),
                    'copy' => number_format($fund->transferAmountVes(), 2, '.', ''),
                ],
                [
                    'label' => 'Banco',
                    'display' => $fund->bankLabel(),
                    'copy' => filled($fund->bank_code) ? (string) $fund->bank_code : null,
                ],
                [
                    'label' => 'Tipo de cuenta',
                    'display' => filled($accountTypeLabel) ? $accountTypeLabel : '—',
                    'copy' => filled($accountTypeLabel) ? $accountTypeLabel : null,
                ],
                [
                    'label' => 'Titular',
                    'display' => filled($fund->bank_account_holder) ? (string) $fund->bank_account_holder : '—',
                    'copy' => filled($fund->bank_account_holder) ? (string) $fund->bank_account_holder : null,
                ],
                [
                    'label' => 'Cédula',
                    'display' => filled($fund->holder_national_id) ? (string) $fund->holder_national_id : '—',
                    'copy' => filled($fund->holder_national_id) ? (string) $fund->holder_national_id : null,
                ],
                [
                    'label' => 'Teléfono',
                    'display' => filled($fund->holder_phone) ? (string) $fund->holder_phone : '—',
                    'copy' => filled($fund->holder_phone) ? (string) $fund->holder_phone : null,
                ],
                [
                    'label' => 'Número de cuenta',
                    'display' => filled($fund->bank_account_number) ? (string) $fund->bank_account_number : '—',
                    'copy' => filled($fund->bank_account_number) ? (string) $fund->bank_account_number : null,
                ],
            ];
        @endphp

        <div class="glass">
            <div class="pay-head">
                <h2 style="margin:0;">Datos para pagar</h2>
            </div>
            <p class="pay-hint">Copie cada dato e ingréselo en Pago Móvil o transferencia.</p>
            @foreach ($paymentFields as $field)
                <div class="pay-row">
                    <span class="k">{{ $field['label'] }}</span>
                    <span class="v">{{ $field['display'] }}</span>
                    @if (filled($field['copy']))
                        <button type="button" class="copy-btn" data-copy="{{ $field['copy'] }}" aria-label="Copiar {{ $field['label'] }}" title="Copiar">
                            <svg class="icon-copy" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.097.291.148.608.148 1.012v.162c0 .252-.168.434-.5.434h-7c-.332 0-.5-.182-.5-.434v-.162c0-.404.051-.721.148-1.012m7.332 0c.636.072 1.217.33 1.684.797A2.25 2.25 0 0 1 18 6.75v12A2.25 2.25 0 0 1 15.75 21H8.25A2.25 2.25 0 0 1 6 18.75v-12A2.25 2.25 0 0 1 8.25 4.5h.375" />
                            </svg>
                            <svg class="icon-check" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="glass">
            <strong style="font-size:0.92rem; letter-spacing:-0.02em;">Motivo</strong>
            <p class="notes" style="margin:10px 0 0;">{{ $fund->notes !== '' ? $fund->notes : '—' }}</p>
        </div>

        @if ($isPending)
            <div class="glass">
                <h2>Aprobar y cargar comprobante</h2>
                <form id="petty-cash-approve-form" method="post" action="{{ $approveUrl }}" enctype="multipart/form-data">
                    @csrf
                    <label class="field-label" for="payment_reference">Referencia o número de comprobante</label>
                    <input id="payment_reference" type="text" name="payment_reference" value="{{ old('payment_reference') }}" required maxlength="255" autocomplete="off">

                    <label class="field-label" for="payment_proof" style="margin-top:14px;">Captura del pago</label>
                    <div class="file-upload" id="payment-proof-upload">
                        <input id="payment_proof" class="file-upload__input" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        <div class="file-upload__surface" id="payment-proof-trigger" role="button" tabindex="0" aria-controls="payment_proof" aria-label="Seleccionar captura del pago">
                            <div class="file-upload__placeholder" id="payment-proof-placeholder">
                                <div class="file-upload__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H4.5A2.25 2.25 0 0 0 2.25 5.25v13.5A2.25 2.25 0 0 0 4.5 21Z" />
                                    </svg>
                                </div>
                                <div class="file-upload__placeholder-text">
                                    <strong>Tocar para elegir archivo</strong>
                                    <span>JPG, PNG, WebP o PDF · máx. 5 MB</span>
                                </div>
                            </div>
                            <div class="file-upload__selected" id="payment-proof-selected" hidden>
                                <div class="file-upload__thumb-wrap">
                                    <img class="file-upload__thumb" id="payment-proof-thumb" alt="" hidden>
                                    <span class="file-upload__pdf" id="payment-proof-pdf" hidden>PDF</span>
                                </div>
                                <div class="file-upload__meta">
                                    <span class="file-upload__name" id="payment-proof-name"></span>
                                    <div class="file-upload__actions">
                                        <button type="button" class="file-upload__btn" id="payment-proof-change">Cambiar</button>
                                        <button type="button" class="file-upload__btn file-upload__btn--ghost" id="payment-proof-clear">Quitar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="hint">Puede ser la captura de la transferencia o el comprobante en PDF.</p>

                    <button class="btn-ok btn-progress" type="submit" disabled aria-disabled="true">
                        <span class="btn-progress__fill" aria-hidden="true"></span>
                        <span class="btn-progress__label">Aprobar y guardar</span>
                    </button>
                </form>
            </div>

            <div class="glass">
                <h2>Rechazar</h2>
                <form id="petty-cash-reject-form" method="post" action="{{ $rejectUrl }}">
                    @csrf
                    <label class="field-label" for="rejection_reason">Motivo del rechazo</label>
                    <textarea id="rejection_reason" name="rejection_reason" maxlength="2000" data-progress-min-chars="12">{{ old('rejection_reason') }}</textarea>
                    <p class="hint">El botón se habilita cuando el motivo esté completo (mínimo 12 caracteres).</p>
                    <button class="btn-no btn-progress" type="submit" disabled aria-disabled="true">
                        <span class="btn-progress__fill" aria-hidden="true"></span>
                        <span class="btn-progress__label">Rechazar solicitud</span>
                    </button>
                </form>
            </div>
        @endif

        <p class="footer-hint">
            Solo administradores autenticados. El enlace caduca a los 7 días.
            <br>
            <a href="{{ $panelUrl }}">Abrir en Farmaadmin</a>
        </p>
    </div>
    <script>
        (function initPaymentProofPreview() {
            const input = document.getElementById('payment_proof');
            const trigger = document.getElementById('payment-proof-trigger');
            const placeholder = document.getElementById('payment-proof-placeholder');
            const selected = document.getElementById('payment-proof-selected');
            const thumb = document.getElementById('payment-proof-thumb');
            const pdfBadge = document.getElementById('payment-proof-pdf');
            const nameEl = document.getElementById('payment-proof-name');
            const changeBtn = document.getElementById('payment-proof-change');
            const clearBtn = document.getElementById('payment-proof-clear');

            if (!input || !trigger || !placeholder || !selected) {
                return;
            }

            let objectUrl = null;

            const revokePreviewUrl = () => {
                if (objectUrl !== null) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }
            };

            const openPicker = () => input.click();

            const resetPreview = () => {
                revokePreviewUrl();
                input.value = '';
                placeholder.hidden = false;
                selected.hidden = true;
                thumb.hidden = true;
                pdfBadge.hidden = true;
                thumb.removeAttribute('src');
                thumb.alt = '';
                nameEl.textContent = '';
            };

            const renderFile = (file) => {
                nameEl.textContent = file.name;
                placeholder.hidden = true;
                selected.hidden = false;

                if (file.type.startsWith('image/')) {
                    revokePreviewUrl();
                    objectUrl = URL.createObjectURL(file);
                    thumb.src = objectUrl;
                    thumb.alt = 'Vista previa del comprobante';
                    thumb.hidden = false;
                    pdfBadge.hidden = true;
                } else {
                    revokePreviewUrl();
                    thumb.hidden = true;
                    thumb.removeAttribute('src');
                    pdfBadge.hidden = false;
                }
            };

            trigger.addEventListener('click', (event) => {
                if (event.target instanceof HTMLElement && event.target.closest('button')) {
                    return;
                }
                if (selected.hidden) {
                    openPicker();
                }
            });

            trigger.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    if (selected.hidden) {
                        openPicker();
                    }
                }
            });

            changeBtn?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openPicker();
            });

            clearBtn?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                resetPreview();
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            input.addEventListener('change', () => {
                const file = input.files?.[0];
                if (!file) {
                    resetPreview();
                    return;
                }
                renderFile(file);
            });
        })();

        (function initProgressSubmitButtons() {
            const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

            const setButtonProgress = (button, percent) => {
                const safe = clamp(Math.round(percent), 0, 100);
                button.style.setProperty('--progress', `${safe}%`);
                button.disabled = safe < 100;
                button.setAttribute('aria-disabled', safe < 100 ? 'true' : 'false');
            };

            const approveForm = document.getElementById('petty-cash-approve-form');
            if (approveForm) {
                const approveButton = approveForm.querySelector('button[type="submit"]');
                const referenceInput = document.getElementById('payment_reference');
                const proofInput = document.getElementById('payment_proof');
                const referenceMinChars = 3;

                const updateApproveProgress = () => {
                    const referencePart = clamp(
                        (referenceInput?.value.trim().length ?? 0) / referenceMinChars,
                        0,
                        1,
                    ) * 50;
                    const proofPart = proofInput?.files?.length ? 50 : 0;
                    setButtonProgress(approveButton, referencePart + proofPart);
                };

                referenceInput?.addEventListener('input', updateApproveProgress);
                proofInput?.addEventListener('change', updateApproveProgress);
                updateApproveProgress();
            }

            const rejectForm = document.getElementById('petty-cash-reject-form');
            if (rejectForm) {
                const rejectButton = rejectForm.querySelector('button[type="submit"]');
                const reasonInput = document.getElementById('rejection_reason');
                const minChars = Number.parseInt(reasonInput?.dataset.progressMinChars ?? '12', 10) || 12;

                const updateRejectProgress = () => {
                    const length = reasonInput?.value.trim().length ?? 0;
                    const percent = clamp(length / minChars, 0, 1) * 100;
                    setButtonProgress(rejectButton, percent);
                };

                reasonInput?.addEventListener('input', updateRejectProgress);
                updateRejectProgress();
            }
        })();

        document.querySelectorAll('.copy-btn[data-copy]').forEach((button) => {
            const originalLabel = button.getAttribute('aria-label') || 'Copiar';

            button.addEventListener('click', async () => {
                const text = button.getAttribute('data-copy') || '';
                if (text === '') {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(text);
                } catch {
                    const input = document.createElement('textarea');
                    input.value = text;
                    input.setAttribute('readonly', '');
                    input.style.position = 'fixed';
                    input.style.left = '-9999px';
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    input.remove();
                }

                button.classList.add('copied');
                button.setAttribute('aria-label', 'Copiado');
                window.setTimeout(() => {
                    button.classList.remove('copied');
                    button.setAttribute('aria-label', originalLabel);
                }, 1600);
            });
        });
    </script>
</body>
</html>
