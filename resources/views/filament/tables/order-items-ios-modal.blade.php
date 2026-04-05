@php
    /** @var array<int, array{name: string, meta: string, quantity: string}> $rows */
    $rows = $rows ?? [];
@endphp

<div class="fi-ios-order-items-modal-inner">
    @include('filament.infolists.components.product-transfer-items-ios', [
        'rows' => $rows,
        'emptyTitle' => 'Sin ítems',
        'emptySub' => 'Este pedido no tiene líneas de producto registradas.',
    ])
</div>
