@php
    use App\Enums\HrPayCurrencyBucket;
    use App\Enums\PayrollLineItemType;

    $employeeName = $employeeName ?? 'Empleado';
    /** @var \App\Models\PayrollLine|null $line */
@endphp

<div class="fi-hr-line-items">
    <div class="fi-hr-line-items__header">
        <p class="fi-hr-line-items__eyebrow">Conceptos del pago</p>
        <p class="fi-hr-line-items__title">{{ $employeeName }}</p>
    </div>

    @if (isset($line))
        <div class="fi-hr-line-items__pay-summary" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem;">
            <div style="border-radius:0.75rem;padding:0.75rem 1rem;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.25);">
                <p style="margin:0;font-size:0.75rem;opacity:0.75;">Pagar en USD</p>
                <p style="margin:0.25rem 0 0;font-weight:700;font-size:1.05rem;">
                    US$ {{ number_format((float) $line->cash_paid_usd, 2, ',', '.') }}
                </p>
                <p style="margin:0.25rem 0 0;font-size:0.75rem;opacity:0.7;">
                    Porción bruta US$ {{ number_format((float) $line->usd_cash_portion, 2, ',', '.') }}
                </p>
            </div>
            <div style="border-radius:0.75rem;padding:0.75rem 1rem;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.25);">
                <p style="margin:0;font-size:0.75rem;opacity:0.75;">Pagar en Bs</p>
                <p style="margin:0.25rem 0 0;font-weight:700;font-size:1.05rem;">
                    Bs {{ number_format((float) $line->cash_paid_ves, 2, ',', '.') }}
                </p>
                <p style="margin:0.25rem 0 0;font-size:0.75rem;opacity:0.7;">
                    Porción US$ {{ number_format((float) $line->ves_portion_usd, 2, ',', '.') }} × BCV
                </p>
            </div>
        </div>
    @endif

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
                $bucket = $item->pay_currency_bucket instanceof HrPayCurrencyBucket
                    ? $item->pay_currency_bucket
                    : (filled($item->pay_currency_bucket)
                        ? HrPayCurrencyBucket::tryFrom((string) $item->pay_currency_bucket)
                        : null);
            @endphp
            <div class="fi-hr-line-items__row fi-hr-line-items__row--{{ $typeClass }}" role="listitem">
                <div class="fi-hr-line-items__body">
                    <span class="fi-hr-line-items__type">{{ $type?->label() ?? 'Concepto' }}</span>
                    <span class="fi-hr-line-items__concept">{{ $item->concept }}</span>
                    @if ($bucket)
                        <span class="fi-hr-line-items__bucket" style="display:inline-block;margin-top:0.25rem;font-size:0.7rem;opacity:0.8;">
                            Bolsillo: {{ $bucket->label() }}
                        </span>
                    @endif
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
