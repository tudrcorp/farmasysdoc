@php
    use Illuminate\Support\Js;

    /** @var \App\Models\AccountsPayable $accountsPayable */
    /** @var \App\Models\Supplier|null $supplier */
    /** @var \Illuminate\Support\Collection<int, \App\Models\SupplierBankAccount> $bankAccounts */
    /** @var float $amountPayableVes */
    /** @var string $supplierName */
    /** @var string|null $supplierTaxId */

    $formatBs = static fn (float $amount): string => 'Bs '.number_format($amount, 2, ',', '.');
    $amountPayableFormatted = number_format($amountPayableVes, 2, ',', '.');
    $amountPayableLabel = $formatBs($amountPayableVes);

    $copyableAttrs = static function (?string $value, string $message = 'Copiado'): ?string {
        if (! filled($value)) {
            return null;
        }

        $text = Js::from($value);
        $tooltip = Js::from($message);

        return <<<HTML
            type="button"
            class="farmadoc-cxp-bank-modal__copy"
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

<div class="farmadoc-cxp-bank-modal space-y-5">
    <div class="farmadoc-cxp-bank-modal__summary grid gap-3 sm:grid-cols-2">
        <div class="farmadoc-cxp-bank-modal__card">
            <p class="farmadoc-cxp-bank-modal__label">Proveedor</p>
            @if ($attrs = $copyableAttrs($supplierName, 'Proveedor copiado'))
                <button {!! $attrs !!}>
                    <span class="farmadoc-cxp-bank-modal__value">{{ $supplierName }}</span>
                </button>
            @else
                <p class="farmadoc-cxp-bank-modal__value farmadoc-cxp-bank-modal__value--muted">—</p>
            @endif
        </div>

        <div class="farmadoc-cxp-bank-modal__card">
            <p class="farmadoc-cxp-bank-modal__label">RIF</p>
            @if ($attrs = $copyableAttrs($supplierTaxId, 'RIF copiado'))
                <button {!! $attrs !!}>
                    <span class="farmadoc-cxp-bank-modal__value farmadoc-cxp-bank-modal__value--mono">{{ $supplierTaxId }}</span>
                </button>
            @else
                <p class="farmadoc-cxp-bank-modal__value farmadoc-cxp-bank-modal__value--muted">—</p>
            @endif
        </div>

        <div class="farmadoc-cxp-bank-modal__card farmadoc-cxp-bank-modal__card--payable sm:col-span-2">
            <p class="farmadoc-cxp-bank-modal__label">Total a pagar</p>
            @if ($attrs = $copyableAttrs($amountPayableFormatted, 'Total a pagar copiado'))
                <button {!! $attrs !!}>
                    <span class="farmadoc-cxp-bank-modal__value farmadoc-cxp-bank-modal__value--payable">{{ $amountPayableLabel }}</span>
                </button>
            @else
                <p class="farmadoc-cxp-bank-modal__value farmadoc-cxp-bank-modal__value--payable">{{ $amountPayableLabel }}</p>
            @endif
            <p class="farmadoc-cxp-bank-modal__hint">Factura {{ $accountsPayable->supplier_invoice_number ?: '—' }} · Clic para copiar el monto</p>
        </div>
    </div>

    <div>
        <div class="farmadoc-cxp-bank-modal__section-head">
            <p class="farmadoc-cxp-bank-modal__section-title">Cuentas bancarias</p>
            <p class="farmadoc-cxp-bank-modal__section-desc">Clic en cualquier dato para copiarlo al portapapeles.</p>
        </div>

        @if ($bankAccounts->isEmpty())
            <div class="farmadoc-cxp-bank-modal__empty">
                <p class="font-medium text-gray-900 dark:text-white">Sin cuentas registradas</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Agregue las cuentas bancarias en la ficha del proveedor para verlas aquí.
                </p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($bankAccounts as $index => $account)
                    <article class="farmadoc-cxp-bank-modal__account">
                        <header class="farmadoc-cxp-bank-modal__account-head">
                            <span class="farmadoc-cxp-bank-modal__account-badge">Cuenta {{ $index + 1 }}</span>
                            @if ($attrs = $copyableAttrs($account->bankLabel(), 'Banco copiado'))
                                <button {!! $attrs !!}>
                                    <span class="farmadoc-cxp-bank-modal__account-bank">{{ $account->bankLabel() }}</span>
                                </button>
                            @endif
                        </header>

                        <dl class="farmadoc-cxp-bank-modal__account-grid">
                            <div>
                                <dt>Código banco</dt>
                                <dd>
                                    @if ($attrs = $copyableAttrs((string) $account->bank_code, 'Código de banco copiado'))
                                        <button {!! $attrs !!}>
                                            <span class="farmadoc-cxp-bank-modal__value farmadoc-cxp-bank-modal__value--mono">{{ $account->bank_code }}</span>
                                        </button>
                                    @else
                                        <span class="farmadoc-cxp-bank-modal__value--muted">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>Número de cuenta</dt>
                                <dd>
                                    @if ($attrs = $copyableAttrs((string) $account->account_number, 'Número de cuenta copiado'))
                                        <button {!! $attrs !!}>
                                            <span class="farmadoc-cxp-bank-modal__value farmadoc-cxp-bank-modal__value--mono">{{ $account->account_number }}</span>
                                        </button>
                                    @else
                                        <span class="farmadoc-cxp-bank-modal__value--muted">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>Teléfono de la cuenta</dt>
                                <dd>
                                    @if ($attrs = $copyableAttrs($account->phone, 'Teléfono de la cuenta copiado'))
                                        <button {!! $attrs !!}>
                                            <span class="farmadoc-cxp-bank-modal__value farmadoc-cxp-bank-modal__value--mono">{{ $account->phone }}</span>
                                        </button>
                                    @else
                                        <span class="farmadoc-cxp-bank-modal__value--muted">Sin teléfono</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>
