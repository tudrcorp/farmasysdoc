<div
    wire:poll.30s="refreshRate"
    class="farmadoc-bcv-rate-badge-root"
    @if (filled($rateDisplay))
        data-farmadoc-bcv-rate-visible="1"
    @endif
>
    @if (filled($rateDisplay))
        <div
            class="farmadoc-bcv-rate-badge"
            role="status"
            aria-live="polite"
            aria-label="Tasa BCV oficial: {{ $rateDisplay }} bolívares por dólar"
            title="Tasa BCV oficial (Bs por 1 USD) · se actualiza cada 30 s"
        >
            <img
                src="{{ $logoUrl }}"
                alt=""
                class="farmadoc-bcv-rate-badge__logo"
                width="34"
                height="34"
                decoding="async"
            />
            <span class="farmadoc-bcv-rate-badge__label">
                <span class="farmadoc-bcv-rate-badge__prefix">
                    <span class="farmadoc-bcv-rate-badge__live" aria-hidden="true"></span>
                    BCV
                </span>
                <span class="farmadoc-bcv-rate-badge__value">{{ $rateDisplay }} <small>Bs/USD</small></span>
            </span>
        </div>
    @else
        <div class="farmadoc-bcv-rate-badge farmadoc-bcv-rate-badge--empty" role="status" title="La tasa BCV no está disponible en este momento">
            <img src="{{ $logoUrl }}" alt="" class="farmadoc-bcv-rate-badge__logo" width="34" height="34" decoding="async" />
            <span class="farmadoc-bcv-rate-badge__label">
                <span class="farmadoc-bcv-rate-badge__prefix">BCV</span>
                <span class="farmadoc-bcv-rate-badge__value"><small>Tasa no disponible</small></span>
            </span>
        </div>
    @endif
</div>
