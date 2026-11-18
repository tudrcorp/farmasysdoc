<div>
    <ol class="pg-steps" aria-label="Pasos del pedido">
        <li @class(['is-on' => $step === 'code'])>1. Código</li>
        <li @class(['is-on' => $step === 'order'])>2. Pedido</li>
        <li @class(['is-on' => $step === 'done'])>3. Comprobante</li>
    </ol>

    @if ($step === 'code')
        <section class="pg-card">
            <h1>Haga su pedido</h1>
            <p class="pg-muted">Escriba el código de su empresa. No hace falta usuario ni clave. El catálogo es el de Farmadoc Las Delicias.</p>
            <form wire:submit="unlock" style="margin-top: 18px;">
                <label for="partner-code">Código del aliado</label>
                <input id="partner-code" wire:model="partnerCode" autocomplete="off" placeholder="Ej. ALDO-2026-001" autofocus>
                @error('partnerCode') <p class="pg-error">{{ $message }}</p> @enderror
                <div class="pg-actions">
                    <button class="pg-btn" type="submit" wire:loading.attr="disabled" wire:target="unlock">Continuar</button>
                </div>
            </form>
        </section>
    @endif

    @if ($step === 'order')
        <section class="pg-card">
            <p class="pg-kicker" style="color: var(--accent);">{{ $companyCode }}</p>
            <h1 style="font-size: 24px;">{{ $companyName }}</h1>
            <p class="pg-muted">Busque por nombre, código o principio activo. El pedido queda pendiente para Las Delicias.</p>
            <div style="margin-top: 14px;">
                <label for="product-search">Buscar producto</label>
                <input id="product-search" wire:model.live.debounce.300ms="search" placeholder="Nombre, código de barras, SKU o principio activo">
            </div>
            @if ($results !== [])
                <div class="pg-results" style="margin-top: 12px;">
                    @foreach ($results as $result)
                        <article class="pg-hit" wire:key="result-{{ $result['id'] }}">
                            <div>
                                <div class="pg-name">{{ $result['name'] }}</div>
                                <div class="pg-meta">{{ $result['code'] }} · {{ $result['ingredient'] }} · USD {{ number_format($result['price'], 2, ',', '.') }}</div>
                            </div>
                            <button class="pg-btn" type="button" wire:click="addProduct({{ $result['id'] }})">Agregar</button>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="pg-card">
            <div style="display: flex; justify-content: space-between; gap: 12px; align-items: center;">
                <h2>Productos</h2>
                <label style="display: flex; gap: 8px; align-items: center; margin: 0;">
                    <input type="checkbox" wire:model.live="isWholesale" style="width: auto;">
                    Pedido al mayor (cajas)
                </label>
            </div>
            @error('lines') <p class="pg-error">{{ $message }}</p> @enderror
            @if ($lines === [])
                <p class="pg-muted">Todavía no hay productos. Use el buscador de arriba.</p>
            @else
                <div class="pg-lines" style="margin-top: 12px;">
                    @foreach ($lines as $index => $line)
                        <article class="pg-line" wire:key="line-{{ $line['product_id'] }}">
                            <div>
                                <div class="pg-name">{{ $line['name'] }}</div>
                                <div class="pg-meta">{{ $line['code'] }}</div>
                            </div>
                            <div class="pg-meta">{{ $line['ingredient'] }}</div>
                            <label class="pg-meta" for="qty-{{ $index }}">{{ $isWholesale ? 'Cajas' : 'Unidades' }}
                                <input class="pg-qty" id="qty-{{ $index }}" type="number" min="1" max="9999" wire:model.number.blur="lines.{{ $index }}.quantity">
                            </label>
                            <button class="pg-link" type="button" wire:click="removeLine({{ $index }})" aria-label="Quitar {{ $line['name'] }}">Quitar</button>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="pg-card">
            <h2>Entrega y pago</h2>
            <div class="pg-grid pg-grid-2" style="margin-top: 12px;">
                <div>
                    <label for="fulfillment">Tipo de entrega</label>
                    <select id="fulfillment" wire:model.live="fulfillment">
                        <option value="delivery">Delivery</option>
                        <option value="pickup">PickUp, retira en el punto acordado</option>
                    </select>
                </div>
                <div>
                    <label for="payment-terms">Forma de pago</label>
                    <select id="payment-terms" wire:model.live="paymentTerms">
                        <option value="contado">De contado</option>
                        <option value="credito">Crédito</option>
                    </select>
                </div>
            </div>

            @if ($paymentTerms === 'contado')
                <div style="margin-top: 12px;">
                    <label for="cash-method">Medio de pago</label>
                    <select id="cash-method" wire:model.live="cashMethod">
                        <option value="pago_movil">Pago móvil</option>
                        <option value="zelle">Zelle</option>
                        <option value="transferencia">Transferencia bancaria</option>
                    </select>
                    @error('cashMethod') <p class="pg-error">{{ $message }}</p> @enderror
                </div>
                @if ($cashMethod === 'pago_movil')
                    <div class="pg-grid pg-grid-2" style="margin-top: 12px; align-items: center;">
                        <img src="{{ $pagoMovilQr }}" alt="Código QR de pago móvil" style="max-width: 220px; border-radius: 12px; border: 1px solid var(--line);">
                        <div>
                            <label for="pm-ref">Referencia del pago móvil</label>
                            <input id="pm-ref" wire:model="pagoMovilReference" placeholder="Referencia o últimos dígitos">
                            @error('pagoMovilReference') <p class="pg-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif
                @if ($cashMethod === 'zelle')
                    <div class="pg-grid pg-grid-2" style="margin-top: 12px; align-items: start;">
                        <img src="{{ $zelleQr }}" alt="Código QR de Zelle" style="max-width: 220px; border-radius: 12px; border: 1px solid var(--line);">
                        <div class="pg-grid">
                            <div>
                                <label for="zelle-name">Nombre de quien envió el Zelle</label>
                                <input id="zelle-name" wire:model="zelleName">
                                @error('zelleName') <p class="pg-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="zelle-ref">Número o referencia</label>
                                <input id="zelle-ref" wire:model="zelleTransaction">
                            </div>
                        </div>
                    </div>
                @endif
                <div style="margin-top: 12px;">
                    <label for="proof">Comprobante (imagen o PDF, máximo 5 MB)</label>
                    <input id="proof" type="file" wire:model="proof" accept="image/jpeg,image/png,image/webp,application/pdf">
                    @error('proof') <p class="pg-error">{{ $message }}</p> @enderror
                </div>
            @endif
        </section>

        <section class="pg-card">
            <h2>{{ $fulfillment === 'pickup' ? 'Quién retira' : 'Quién recibe' }}</h2>
            <div class="pg-grid pg-grid-2" style="margin-top: 12px;">
                <div>
                    <label for="recipient">Nombre</label>
                    <input id="recipient" wire:model="recipientName">
                    @error('recipientName') <p class="pg-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone">Teléfono</label>
                    <input id="phone" wire:model="recipientPhone">
                    @error('recipientPhone') <p class="pg-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="document">Cédula o RIF</label>
                    <input id="document" wire:model="recipientDocument" placeholder="Opcional">
                </div>
                <div>
                    <label for="city">Ciudad</label>
                    <input id="city" wire:model="city">
                </div>
            </div>
            @if ($fulfillment === 'delivery')
                <div style="margin-top: 12px;">
                    <label for="address">Dirección de entrega</label>
                    <input id="address" wire:model="address" placeholder="Calle, edificio, apartamento">
                    @error('address') <p class="pg-error">{{ $message }}</p> @enderror
                </div>
            @endif
            <div class="pg-grid pg-grid-2" style="margin-top: 12px;">
                <div>
                    <label for="state">Estado</label>
                    <input id="state" wire:model="state">
                </div>
                <div>
                    <label for="notes">Indicaciones</label>
                    <input id="notes" wire:model="deliveryNotes" placeholder="Horario, portería, punto de referencia">
                </div>
            </div>
        </section>

        <section class="pg-card">
            <div class="pg-summary">
                <div>
                    <p class="pg-muted" style="margin: 0;">{{ $summary['count'] }} {{ $summary['count'] === 1 ? 'producto' : 'productos' }} · Impuesto USD {{ number_format($summary['tax_total'], 2, ',', '.') }} · Descuento USD {{ number_format($summary['discount_total'], 2, ',', '.') }}</p>
                    <p class="pg-total">USD {{ number_format($summary['total'], 2, ',', '.') }}</p>
                </div>
                <button class="pg-btn" type="button" wire:click="place" wire:loading.attr="disabled" wire:target="place">Confirmar pedido</button>
            </div>
            @error('fulfillment') <p class="pg-error">{{ $message }}</p> @enderror
        </section>
    @endif

    @if ($step === 'done')
        <section class="pg-card">
            <div class="pg-check" aria-hidden="true">✓</div>
            <h1 style="margin-top: 14px;">Pedido registrado</h1>
            <p class="pg-muted">Quedó en estado <strong>Pendiente</strong>, a nombre de {{ $companyName }}, con sucursal Farmadoc Las Delicias.</p>
            <p class="pg-total">{{ $orderNumber }}</p>
            <div class="pg-actions">
                @if ($pdfUrl)
                    <a class="pg-btn" href="{{ $pdfUrl }}">Descargar PDF del pedido</a>
                @endif
                <button class="pg-btn-ghost" type="button" wire:click="another">Hacer otro pedido</button>
                <button class="pg-link" type="button" wire:click="leave">Salir</button>
            </div>
            <p class="pg-meta">El PDF incluye el detalle del pedido. Puede descargarlo durante las próximas 48 horas desde este enlace.</p>
        </section>
    @endif
</div>
