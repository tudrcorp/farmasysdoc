<button
    type="button"
    wire:click="toggleSalesPaymentMethodTabs"
    class="farmadoc-ios-sales-payment-tabs__handle"
    aria-controls="sales-payment-method-tabs"
    aria-expanded="{{ $showSalesPaymentMethodTabs ? 'true' : 'false' }}"
    title="{{ $showSalesPaymentMethodTabs ? 'Ocultar totales por forma de pago' : 'Mostrar totales por forma de pago' }}"
>
    <span class="farmadoc-ios-sales-payment-tabs__handle-bar" aria-hidden="true"></span>
    <span class="sr-only">
        {{ $showSalesPaymentMethodTabs ? 'Ocultar' : 'Mostrar' }} pestañas de formas de pago
    </span>
</button>
