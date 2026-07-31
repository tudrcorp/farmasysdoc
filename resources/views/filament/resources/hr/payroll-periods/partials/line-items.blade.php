@php
    use App\Enums\PayrollLineItemType;

    $employeeName = $employeeName ?? 'Empleado';
@endphp

<div class="fi-hr-line-items">
    <div class="fi-hr-line-items__header">
        <p class="fi-hr-line-items__eyebrow">Conceptos del pago</p>
        <p class="fi-hr-line-items__title">{{ $employeeName }}</p>
    </div>

    <div class="fi-hr-line-items__list" role="list">
        @forelse ($items as $item)
            @php
                $type = $item->type instanceof PayrollLineItemType
                    ? $item->type
                    : PayrollLineItemType::tryFrom((string) $item->type);
                $isNegative = in_array($type, [PayrollLineItemType::Deduction, PayrollLineItemType::Loan], true);
                $typeClass = match ($type) {
                    PayrollLineItemType::Base => 'is-base',
                    PayrollLineItemType::Assignment => 'is-plus',
                    PayrollLineItemType::Deduction => 'is-minus',
                    PayrollLineItemType::Loan => 'is-loan',
                    default => 'is-base',
                };
            @endphp
            <div class="fi-hr-line-items__row fi-hr-line-items__row--{{ $typeClass }}" role="listitem">
                <div class="fi-hr-line-items__body">
                    <span class="fi-hr-line-items__type">{{ $type?->label() ?? 'Concepto' }}</span>
                    <span class="fi-hr-line-items__concept">{{ $item->concept }}</span>
                </div>
                <div class="fi-hr-line-items__amounts">
                    <span class="fi-hr-line-items__usd">
                        {{ $isNegative ? '−' : '+' }}
                        US$ {{ number_format((float) $item->amount_usd, 2, ',', '.') }}
                    </span>
                    <span class="fi-hr-line-items__ves">
                        {{ $isNegative ? '−' : '+' }}
                        Bs {{ number_format((float) $item->amount_ves, 2, ',', '.') }}
                    </span>
                </div>
            </div>
        @empty
            <div class="fi-hr-line-items__empty">
                <p>Sin conceptos calculados para este empleado.</p>
            </div>
        @endforelse
    </div>
</div>
