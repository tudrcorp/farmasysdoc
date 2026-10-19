@php
    use App\Filament\Resources\AccountsPayables\AccountsPayableResource;
    use App\Filament\Resources\PurchaseBooks\PurchaseBookResource;

    $money = static fn (float $value): string => number_format($value, 2, ',', '.');
    $accountsPayableUrl = static fn (?int $id): ?string => $canOpenAccountsPayables && $id !== null
        ? AccountsPayableResource::getUrl('view', ['record' => $id])
        : null;
    $location = collect([$supplier->address, $supplier->city, $supplier->state, $supplier->country])->filter()->implode(', ');
    $tabs = [
        'summary' => ['label' => 'Resumen', 'count' => null],
        'purchases' => ['label' => 'Compras', 'count' => count($purchases)],
        'payments' => ['label' => 'Facturas pagadas', 'count' => count($payments)],
        'proofs' => ['label' => 'Comprobantes', 'count' => count($payment_proofs)],
        'retentions' => ['label' => 'Retenciones', 'count' => count($retentions)],
    ];
@endphp

<div class="fi-farmaadmin-supplier-dossier" x-data="{ tab: 'summary' }">
    @if ($is_branch_scoped)
        <p class="fi-farmaadmin-supplier-dossier__scope">
            <x-filament::icon icon="heroicon-o-funnel" class="size-4" />
            Solo se incluyen compras de las sucursales asignadas a tu usuario.
        </p>
    @endif

    <div class="fi-farmaadmin-supplier-dossier__kpis">
        <div class="fi-farmaadmin-supplier-dossier__kpi">
            <span class="fi-farmaadmin-supplier-dossier__kpi-label">Total comprado</span>
            <span class="fi-farmaadmin-supplier-dossier__kpi-value">${{ $money($summary['purchased_usd']) }}</span>
            <span class="fi-farmaadmin-supplier-dossier__kpi-hint">Bs {{ $money($summary['purchased_ves']) }} · {{ $summary['purchases_count'] }} compra(s)</span>
        </div>
        <div class="fi-farmaadmin-supplier-dossier__kpi fi-farmaadmin-supplier-dossier__kpi--success">
            <span class="fi-farmaadmin-supplier-dossier__kpi-label">Total pagado</span>
            <span class="fi-farmaadmin-supplier-dossier__kpi-value">${{ $money($summary['paid_usd']) }}</span>
            <span class="fi-farmaadmin-supplier-dossier__kpi-hint">Bs {{ $money($summary['paid_ves']) }} · {{ $summary['paid_invoices_count'] }} factura(s)</span>
        </div>
        <div @class([
            'fi-farmaadmin-supplier-dossier__kpi',
            'fi-farmaadmin-supplier-dossier__kpi--warning' => $summary['pending_usd'] > 0,
        ])>
            <span class="fi-farmaadmin-supplier-dossier__kpi-label">Pendiente por pagar</span>
            <span class="fi-farmaadmin-supplier-dossier__kpi-value">${{ $money($summary['pending_usd']) }}</span>
            <span class="fi-farmaadmin-supplier-dossier__kpi-hint">Bs {{ $money($summary['pending_ves']) }} · {{ $summary['pending_invoices_count'] }} factura(s)</span>
        </div>
        <div class="fi-farmaadmin-supplier-dossier__kpi">
            <span class="fi-farmaadmin-supplier-dossier__kpi-label">IVA retenido</span>
            <span class="fi-farmaadmin-supplier-dossier__kpi-value">Bs {{ $money($summary['retained_ves']) }}</span>
            <span class="fi-farmaadmin-supplier-dossier__kpi-hint">{{ $summary['retentions_count'] }} comprobante(s)</span>
        </div>
        <div class="fi-farmaadmin-supplier-dossier__kpi">
            <span class="fi-farmaadmin-supplier-dossier__kpi-label">Última compra</span>
            <span class="fi-farmaadmin-supplier-dossier__kpi-value">{{ $summary['last_purchase_at'] ?? '—' }}</span>
            <span class="fi-farmaadmin-supplier-dossier__kpi-hint">Primera {{ $summary['first_purchase_at'] ?? '—' }} · Últ. pago {{ $summary['last_payment_at'] ?? '—' }}</span>
        </div>
    </div>

    <nav class="fi-farmaadmin-supplier-dossier__tabs" role="tablist">
        @foreach ($tabs as $key => $tabConfig)
            <button
                type="button"
                role="tab"
                class="fi-farmaadmin-supplier-dossier__tab"
                x-on:click="tab = '{{ $key }}'"
                x-bind:class="{ 'fi-farmaadmin-supplier-dossier__tab--active': tab === '{{ $key }}' }"
                x-bind:aria-selected="tab === '{{ $key }}'"
            >
                {{ $tabConfig['label'] }}
                @if ($tabConfig['count'] !== null)
                    <span class="fi-farmaadmin-supplier-dossier__tab-count">{{ $tabConfig['count'] }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    <section x-show="tab === 'summary'" class="fi-farmaadmin-supplier-dossier__panel">
        <dl class="fi-farmaadmin-supplier-dossier__facts">
            <div>
                <dt>Razón social</dt>
                <dd>{{ $supplier->legal_name ?: '—' }}</dd>
            </div>
            <div>
                <dt>Nombre comercial</dt>
                <dd>{{ $supplier->trade_name ?: '—' }}</dd>
            </div>
            <div>
                <dt>RIF / identificación fiscal</dt>
                <dd>{{ $supplier->tax_id ?: '—' }}</dd>
            </div>
            <div>
                <dt>Retención IVA (SENIAT)</dt>
                <dd>
                    @if ($supplier->seniat_retention_percent !== null)
                        {{ number_format((float) $supplier->seniat_retention_percent, 2, ',', '.') }}%
                    @else
                        <span class="fi-farmaadmin-supplier-dossier__warn">Sin configurar</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt>Correo</dt>
                <dd>{{ $supplier->email ?: '—' }}</dd>
            </div>
            <div>
                <dt>Teléfono / celular</dt>
                <dd>{{ collect([$supplier->phone, $supplier->mobile_phone])->filter()->implode(' · ') ?: '—' }}</dd>
            </div>
            <div>
                <dt>Sitio web</dt>
                <dd>
                    @if (filled($supplier->website))
                        <a href="{{ $supplier->website }}" target="_blank" rel="noopener" class="fi-farmaadmin-supplier-dossier__link">{{ $supplier->website }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt>Ubicación</dt>
                <dd>{{ $location ?: '—' }}</dd>
            </div>
            <div>
                <dt>Contacto comercial</dt>
                <dd>
                    {{ $supplier->contact_name ?: '—' }}
                    @if (filled($supplier->contact_email) || filled($supplier->contact_phone))
                        <span class="fi-farmaadmin-supplier-dossier__muted">
                            {{ collect([$supplier->contact_email, $supplier->contact_phone])->filter()->implode(' · ') }}
                        </span>
                    @endif
                </dd>
            </div>
            <div>
                <dt>Condiciones de pago</dt>
                <dd>{{ $supplier->payment_terms ?: '—' }}</dd>
            </div>
            <div class="fi-farmaadmin-supplier-dossier__facts-wide">
                <dt>Cuentas bancarias</dt>
                <dd>
                    @forelse ($supplier->bankAccounts as $bankAccount)
                        <span class="fi-farmaadmin-supplier-dossier__chip">
                            {{ $bankAccount->displayLabel() }}
                            @if (filled($bankAccount->phone))
                                · Pago móvil {{ $bankAccount->phone }}
                            @endif
                        </span>
                    @empty
                        —
                    @endforelse
                </dd>
            </div>
            @if (filled($supplier->notes))
                <div class="fi-farmaadmin-supplier-dossier__facts-wide">
                    <dt>Notas</dt>
                    <dd>{{ $supplier->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <section x-show="tab === 'purchases'" x-cloak class="fi-farmaadmin-supplier-dossier__panel">
        @if ($purchases === [])
            <p class="fi-farmaadmin-supplier-dossier__empty">Sin compras registradas.</p>
        @else
            <div class="fi-farmaadmin-supplier-dossier__table-wrap">
                <table class="fi-farmaadmin-supplier-dossier__table">
                    <thead>
                        <tr>
                            <th>Nº orden</th>
                            <th>Factura</th>
                            <th>Sucursal</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th class="is-num">Total documento</th>
                            <th class="is-num">Total USD</th>
                            <th>Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchases as $purchase)
                            @php($url = $accountsPayableUrl($purchase['accounts_payable_id']))
                            <tr @class(['is-muted' => $purchase['is_excluded']])>
                                <td>
                                    @if ($url)
                                        <a href="{{ $url }}" class="fi-farmaadmin-supplier-dossier__link">{{ $purchase['purchase_number'] }}</a>
                                    @else
                                        {{ $purchase['purchase_number'] }}
                                    @endif
                                </td>
                                <td>{{ $purchase['invoice_number'] ?? '—' }}</td>
                                <td>{{ $purchase['branch_name'] }}</td>
                                <td>{{ $purchase['date'] ?? '—' }}</td>
                                <td>{{ $purchase['status'] }}</td>
                                <td class="is-num">{{ $purchase['document_total'] }}</td>
                                <td class="is-num">{{ $purchase['total_usd'] !== null ? '$'.$money($purchase['total_usd']) : '—' }}</td>
                                <td>{{ $purchase['payment_status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">Total comprado (sin borradores, cancelaciones ni anulaciones)</td>
                            <td class="is-num">${{ $money($summary['purchased_usd']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    <section x-show="tab === 'payments'" x-cloak class="fi-farmaadmin-supplier-dossier__panel">
        @if ($payments === [])
            <p class="fi-farmaadmin-supplier-dossier__empty">Sin pagos registrados.</p>
        @else
            <div class="fi-farmaadmin-supplier-dossier__table-wrap">
                <table class="fi-farmaadmin-supplier-dossier__table">
                    <thead>
                        <tr>
                            <th>Fecha de pago</th>
                            <th>Factura</th>
                            <th>Sucursal</th>
                            <th>Método</th>
                            <th>Banco / cuenta</th>
                            <th>Referencia</th>
                            <th class="is-num">USD</th>
                            <th class="is-num">Bs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            @php($url = $accountsPayableUrl($payment['accounts_payable_id']))
                            <tr>
                                <td>{{ $payment['paid_at'] ?? '—' }}</td>
                                <td>
                                    @if ($url)
                                        <a href="{{ $url }}" class="fi-farmaadmin-supplier-dossier__link">{{ $payment['invoice_number'] ?? '—' }}</a>
                                    @else
                                        {{ $payment['invoice_number'] ?? '—' }}
                                    @endif
                                </td>
                                <td>{{ $payment['branch_name'] }}</td>
                                <td>{{ $payment['payment_method'] }}</td>
                                <td>{{ $payment['payment_bank'] ?? '—' }}</td>
                                <td>{{ $payment['payment_reference'] ?? '—' }}</td>
                                <td class="is-num">${{ $money($payment['amount_usd']) }}</td>
                                <td class="is-num">Bs {{ $money($payment['amount_ves']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">Total pagado</td>
                            <td class="is-num">${{ $money($summary['paid_usd']) }}</td>
                            <td class="is-num">Bs {{ $money($summary['paid_ves']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    <section x-show="tab === 'proofs'" x-cloak class="fi-farmaadmin-supplier-dossier__panel">
        @if ($payment_proofs === [])
            <p class="fi-farmaadmin-supplier-dossier__empty">Sin comprobantes de pago adjuntos.</p>
        @else
            <div class="fi-farmaadmin-supplier-dossier__table-wrap">
                <table class="fi-farmaadmin-supplier-dossier__table">
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Sucursal</th>
                            <th>Fecha de pago</th>
                            <th>Referencia</th>
                            <th class="is-num">Total USD</th>
                            <th>Comprobante</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payment_proofs as $proof)
                            @php($url = $accountsPayableUrl($proof['accounts_payable_id']))
                            <tr>
                                <td>
                                    @if ($url)
                                        <a href="{{ $url }}" class="fi-farmaadmin-supplier-dossier__link">{{ $proof['invoice_number'] ?? '—' }}</a>
                                    @else
                                        {{ $proof['invoice_number'] ?? '—' }}
                                    @endif
                                </td>
                                <td>{{ $proof['branch_name'] }}</td>
                                <td>{{ $proof['paid_at'] ?? '—' }}</td>
                                <td>{{ $proof['payment_reference'] ?? '—' }}</td>
                                <td class="is-num">${{ $money($proof['amount_usd']) }}</td>
                                <td>
                                    @if ($proof['proof_url'])
                                        <a href="{{ $proof['proof_url'] }}" target="_blank" rel="noopener" class="fi-farmaadmin-supplier-dossier__file">
                                            <x-filament::icon
                                                :icon="$proof['proof_is_pdf'] ? 'heroicon-o-document-text' : 'heroicon-o-photo'"
                                                class="size-4"
                                            />
                                            {{ $proof['proof_is_pdf'] ? 'Ver PDF' : 'Ver imagen' }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section x-show="tab === 'retentions'" x-cloak class="fi-farmaadmin-supplier-dossier__panel">
        @if ($retentions === [])
            <p class="fi-farmaadmin-supplier-dossier__empty">Sin retenciones registradas en el Libro de Compras.</p>
        @else
            <div class="fi-farmaadmin-supplier-dossier__table-wrap">
                <table class="fi-farmaadmin-supplier-dossier__table">
                    <thead>
                        <tr>
                            <th>Comprobante</th>
                            <th>Emisión</th>
                            <th>Período</th>
                            <th>Factura</th>
                            <th>Fecha factura</th>
                            <th class="is-num">Base imponible</th>
                            <th class="is-num">IVA causado</th>
                            <th class="is-num">% ret.</th>
                            <th class="is-num">IVA retenido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($retentions as $retention)
                            <tr>
                                <td>
                                    @if ($canOpenPurchaseBooks)
                                        <a href="{{ PurchaseBookResource::getUrl('view', ['record' => $retention['purchase_book_id']]) }}" class="fi-farmaadmin-supplier-dossier__link">
                                            {{ $retention['voucher_number'] ?? '—' }}
                                        </a>
                                    @else
                                        {{ $retention['voucher_number'] ?? '—' }}
                                    @endif
                                </td>
                                <td>{{ $retention['issue_date'] ?? '—' }}</td>
                                <td>{{ $retention['tax_period'] ?? '—' }}</td>
                                <td>{{ $retention['invoice_number'] ?? '—' }}</td>
                                <td>{{ $retention['invoice_date'] ?? '—' }}</td>
                                <td class="is-num">Bs {{ $money($retention['taxable_base_ves']) }}</td>
                                <td class="is-num">Bs {{ $money($retention['tax_caused_ves']) }}</td>
                                <td class="is-num">{{ $retention['retention_percent'] !== null ? number_format($retention['retention_percent'], 0, ',', '.').'%' : '—' }}</td>
                                <td class="is-num">Bs {{ $money($retention['tax_retained_ves']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="8">Total retenido</td>
                            <td class="is-num">Bs {{ $money($summary['retained_ves']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>
</div>
