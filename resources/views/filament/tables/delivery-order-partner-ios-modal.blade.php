@php
    /** @var list<array{title: string, rows: list<array{label: string, value: string}>}> $sections */
    $sections = $sections ?? [];
@endphp

<div class="fi-ios-delivery-order-modal-inner">
    @if (count($sections) === 0)
        <div class="fi-ios-delivery-order__empty">
            <p class="fi-ios-delivery-order__empty-title">Sin datos del pedido</p>
            <p class="fi-ios-delivery-order__empty-sub">No hay información de envío del aliado disponible para esta entrega.</p>
        </div>
    @else
        @foreach ($sections as $section)
            <div class="fi-ios-delivery-order__block">
                <p class="fi-ios-delivery-order__block-title">{{ $section['title'] }}</p>
                <div class="fi-ios-delivery-order__card">
                    @foreach ($section['rows'] as $idx => $row)
                        @if ($idx > 0)
                            <div class="fi-ios-delivery-order__hairline" aria-hidden="true"></div>
                        @endif
                        <div class="fi-ios-delivery-order__item">
                            <span class="fi-ios-delivery-order__label">{{ $row['label'] }}</span>
                            <span class="fi-ios-delivery-order__value">{{ $row['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>
