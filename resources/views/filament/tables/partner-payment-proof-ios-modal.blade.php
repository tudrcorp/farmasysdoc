@php
    /** @var string $url */
    /** @var bool $isPdf */
    /** @var string $fileName */
    $url = $url ?? '';
    $isPdf = $isPdf ?? false;
    $fileName = $fileName ?? 'comprobante';
@endphp

<div class="fi-ios-payment-proof-modal-inner">
    @if ($url === '')
        <div class="fi-ios-delivery-order__empty rounded-2xl border border-zinc-200/80 bg-zinc-50/80 p-4 dark:border-white/10 dark:bg-white/5">
            <p class="fi-ios-delivery-order__empty-title text-sm font-semibold text-zinc-900 dark:text-white">
                Sin archivo
            </p>
            <p class="fi-ios-delivery-order__empty-sub mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                No hay una ruta de comprobante disponible para este pedido.
            </p>
        </div>
    @else
        <div class="fi-ios-payment-proof__viewer">
            @if ($isPdf)
                <div class="fi-ios-payment-proof__frame-shell">
                    <iframe
                        src="{{ $url }}#toolbar=0"
                        class="fi-ios-payment-proof__iframe"
                        title="Vista previa del comprobante PDF"
                        loading="lazy"
                    ></iframe>
                </div>
            @else
                <div class="fi-ios-payment-proof__img-shell">
                    <img
                        src="{{ $url }}"
                        alt="Comprobante de pago"
                        class="fi-ios-payment-proof__img"
                        loading="lazy"
                    />
                </div>
            @endif
        </div>

        <div class="fi-ios-delivery-order__block mt-6 border-t border-zinc-200/70 pt-6 dark:border-white/10">
            <p class="fi-ios-delivery-order__block-title">Detalle</p>
            <div class="fi-ios-delivery-order__card">
                <div class="fi-ios-delivery-order__item">
                    <span class="fi-ios-delivery-order__label">Archivo</span>
                    <span class="fi-ios-delivery-order__value break-all">{{ $fileName }}</span>
                </div>
                <div class="fi-ios-delivery-order__hairline" aria-hidden="true"></div>
                <div class="fi-ios-delivery-order__item">
                    <span class="fi-ios-delivery-order__label">Tipo</span>
                    <span class="fi-ios-delivery-order__value">{{ $isPdf ? 'PDF' : 'Imagen' }}</span>
                </div>
            </div>
        </div>

        <a
            href="{{ $url }}"
            target="_blank"
            rel="noopener noreferrer"
            class="fi-ios-payment-proof__external-link"
        >
            <span class="fi-ios-payment-proof__external-link-label">Abrir en nueva pestaña</span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="fi-ios-payment-proof__external-icon" aria-hidden="true">
                <path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd" />
                <path fill-rule="evenodd" d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z" clip-rule="evenodd" />
            </svg>
        </a>
    @endif
</div>
