@php
    use App\Enums\HrPayCurrencyBucket;
    use App\Enums\PayrollLineItemType;
    use Illuminate\Support\Js;

    $employeeName = $employeeName ?? 'Empleado';
    /** @var \App\Models\PayrollLine|null $line */

    $copyableAttrs = static function (?string $value, string $message = 'Copiado'): ?string {
        if (! filled($value)) {
            return null;
        }

        $text = Js::from($value);
        $tooltip = Js::from($message);

        return <<<HTML
            type="button"
            class="fi-hr-line-items__copyable"
            title="Clic para copiar"
            x-on:click="
                window.navigator.clipboard.writeText({$text})
                \$tooltip({$tooltip}, {
                    theme: \$store.theme,
                    timeout: 1500,
                })
            "
        HTML;
    };
@endphp

<div class="fi-hr-line-items">
    <div class="fi-hr-line-items__header">
        <p class="fi-hr-line-items__eyebrow">Conceptos del pago</p>
        <p class="fi-hr-line-items__title">{{ $employeeName }}</p>
    </div>

    @if (isset($line))
        @php
            $employee = $line->employee;
            $bank = $employee?->bank();
            $accountType = $employee?->bank_account_type;
            $accountTypeLabel = $accountType?->label() ?? null;
            $bankName = $bank?->bankName();
            $bankCode = filled($employee?->bank_code) ? (string) $employee->bank_code : null;
            $accountNumber = filled($employee?->bank_account_number) ? (string) $employee->bank_account_number : null;
            $nationalId = filled($employee?->national_id) ? (string) $employee->national_id : null;
            $phone = filled($employee?->phone) ? (string) $employee->phone : null;
            $email = filled($employee?->email) ? (string) $employee->email : null;
            $hasBanking = filled($bankCode) || filled($accountNumber) || filled($accountTypeLabel);
        @endphp

        @if ($employee)
            <section class="fi-hr-line-items__profile">
                <div class="fi-hr-line-items__profile-head">
                    <p class="fi-hr-line-items__profile-eyebrow">Datos del empleado</p>
                    <p class="fi-hr-line-items__profile-title">Contacto y cuenta bancaria</p>
                </div>

                <div class="fi-hr-line-items__contact-grid">
                    <div class="fi-hr-line-items__field">
                        <span class="fi-hr-line-items__field-label">Cédula</span>
                        @if ($nationalId)
                            <button {!! $copyableAttrs($nationalId, 'Cédula copiada') !!}>
                                <span class="fi-hr-line-items__field-value">{{ $nationalId }}</span>
                            </button>
                        @else
                            <span class="fi-hr-line-items__field-value fi-hr-line-items__field-value--empty">—</span>
                        @endif
                    </div>
                    <div class="fi-hr-line-items__field">
                        <span class="fi-hr-line-items__field-label">Teléfono</span>
                        @if ($phone)
                            <button {!! $copyableAttrs($phone, 'Teléfono copiado') !!}>
                                <span class="fi-hr-line-items__field-value">{{ $phone }}</span>
                            </button>
                        @else
                            <span class="fi-hr-line-items__field-value fi-hr-line-items__field-value--empty">—</span>
                        @endif
                    </div>
                    <div class="fi-hr-line-items__field fi-hr-line-items__field--wide">
                        <span class="fi-hr-line-items__field-label">Email</span>
                        @if ($email)
                            <button {!! $copyableAttrs($email, 'Email copiado') !!}>
                                <span class="fi-hr-line-items__field-value fi-hr-line-items__field-value--wrap">{{ $email }}</span>
                            </button>
                        @else
                            <span class="fi-hr-line-items__field-value fi-hr-line-items__field-value--empty">—</span>
                        @endif
                    </div>
                </div>

                <div class="fi-hr-line-items__bank @if (! $hasBanking) fi-hr-line-items__bank--empty @endif">
                    <div class="fi-hr-line-items__bank-main">
                        <div class="fi-hr-line-items__field">
                            <span class="fi-hr-line-items__field-label">Banco</span>
                            @if ($bankName)
                                <button {!! $copyableAttrs($bankName, 'Banco copiado') !!}>
                                    <span class="fi-hr-line-items__field-value">{{ $bankName }}</span>
                                </button>
                            @elseif ($bankCode)
                                <button {!! $copyableAttrs($bankCode, 'Código de banco copiado') !!}>
                                    <span class="fi-hr-line-items__field-value">Banco {{ $bankCode }}</span>
                                </button>
                            @else
                                <span class="fi-hr-line-items__field-value fi-hr-line-items__field-value--empty">Sin banco</span>
                            @endif
                        </div>
                        <div class="fi-hr-line-items__bank-badges">
                            @if ($bankCode)
                                <button {!! $copyableAttrs($bankCode, 'Código de banco copiado') !!}>
                                    <span class="fi-hr-line-items__chip fi-hr-line-items__chip--code">{{ $bankCode }}</span>
                                </button>
                            @endif
                            @if (filled($accountTypeLabel))
                                <button {!! $copyableAttrs($accountTypeLabel, 'Tipo de cuenta copiado') !!}>
                                    <span class="fi-hr-line-items__chip fi-hr-line-items__chip--type">{{ $accountTypeLabel }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="fi-hr-line-items__field fi-hr-line-items__field--account">
                        <span class="fi-hr-line-items__field-label">Número de cuenta</span>
                        @if ($accountNumber)
                            <button {!! $copyableAttrs($accountNumber, 'Número de cuenta copiado') !!}>
                                <span class="fi-hr-line-items__account-number">{{ $accountNumber }}</span>
                            </button>
                        @else
                            <span class="fi-hr-line-items__account-number fi-hr-line-items__field-value--empty">Sin número de cuenta</span>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <div class="fi-hr-line-items__pay-summary">
            <div class="fi-hr-line-items__pay-card fi-hr-line-items__pay-card--usd">
                <p class="fi-hr-line-items__pay-label">Pagar en USD</p>
                <p class="fi-hr-line-items__pay-value">
                    US$ {{ number_format((float) $line->cash_paid_usd, 2, ',', '.') }}
                </p>
                <p class="fi-hr-line-items__pay-hint">
                    Porción {{ number_format((float) $line->usd_cash_portion, 2, ',', '.') }} USD
                </p>
            </div>
            <div class="fi-hr-line-items__pay-card fi-hr-line-items__pay-card--ves">
                <p class="fi-hr-line-items__pay-label">Pagar en Bs</p>
                <p class="fi-hr-line-items__pay-value">
                    Bs {{ number_format((float) $line->cash_paid_ves, 2, ',', '.') }}
                </p>
                <p class="fi-hr-line-items__pay-hint">
                    Porción US$ {{ number_format((float) $line->ves_portion_usd, 2, ',', '.') }}
                    · Bs {{ number_format((float) $line->ves_portion_usd * (float) $line->bcv_ves_per_usd, 2, ',', '.') }}
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
