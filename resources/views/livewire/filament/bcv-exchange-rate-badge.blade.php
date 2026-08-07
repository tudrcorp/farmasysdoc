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
            aria-label="Tasa BCV oficial: {{ $rateDisplay }} VES por dólar"
            title="Tasa BCV oficial (VES por 1 USD) · actualización en vivo"
        >
            <span class="farmadoc-bcv-rate-badge__halo" aria-hidden="true"></span>
            <img
                src="{{ $logoUrl }}"
                alt=""
                class="farmadoc-bcv-rate-badge__logo"
                width="32"
                height="32"
                decoding="async"
            />
            <span class="farmadoc-bcv-rate-badge__label">
                <span class="farmadoc-bcv-rate-badge__prefix">BCV</span>
                <span class="farmadoc-bcv-rate-badge__value">{{ $rateDisplay }} VES</span>
            </span>
        </div>
    @endif
</div>
