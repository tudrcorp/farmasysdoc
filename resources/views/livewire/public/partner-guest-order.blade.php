<div>
    <ol class="pg-steps" aria-label="Pasos del pedido">
        <li @class(['is-done' => $step !== 'code', 'is-on' => $step === 'code'])>1. Código</li>
        <li @class(['is-done' => $step === 'done', 'is-on' => $step === 'order'])>2. Pedido</li>
        <li @class(['is-on' => $step === 'done'])>3. Listo</li>
    </ol>

    @if ($step === 'code')
        <section class="pg-card pg-gate">
            <p class="pg-kicker" style="color: var(--accent);">Aliados Farmadoc</p>
            <h1>Arme el pedido con su código</h1>
            <p class="pg-muted">Sin usuario ni clave. El catálogo y la entrega salen de Farmadoc Las Delicias.</p>
            <form wire:submit="unlock" style="margin-top: 22px;">
                <label for="partner-code">Código del aliado</label>
                <input id="partner-code" wire:model="partnerCode" autocomplete="off" placeholder="Ej. ALDO-2026-001" autofocus>
                @error('partnerCode') <p class="pg-error">{{ $message }}</p> @enderror
                <div class="pg-actions">
                    <button class="pg-btn pg-btn-block" type="submit" wire:loading.attr="disabled" wire:target="unlock">
                        <span wire:loading.remove wire:target="unlock">Ver catálogo</span>
                        <span wire:loading wire:target="unlock">Revisando código…</span>
                    </button>
                </div>
            </form>
        </section>
    @endif

    @if ($step === 'order')
        <div class="pg-shop">
            <section class="pg-card pg-catalog">
                <p class="pg-kicker" style="color: var(--accent);">{{ $companyCode }}</p>
                <h1 style="font-size: 28px;">{{ $companyName }}</h1>
                <p class="pg-muted">Busque y agregue. El pedido se arma a la derecha y queda pendiente.</p>
                <div class="pg-search" style="margin-top: 16px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <label class="sr-only" for="product-search" style="position:absolute;width:1px;height:1px;overflow:hidden;">Buscar producto</label>
                    <input id="product-search" wire:model.live.debounce.300ms="search" placeholder="Nombre, código o principio activo">
                </div>
                <p class="pg-meta" wire:loading wire:target="search">Buscando…</p>
                @if ($results !== [])
                    <div class="pg-results">
                        @foreach ($results as $result)
                            <article class="pg-hit" wire:key="result-{{ $result['id'] }}">
                                <div>
                                    <div class="pg-name">{{ $result['name'] }}</div>
                                    <div class="pg-meta">{{ $result['code'] }}@if ($result['ingredient'] !== '—') · {{ $result['ingredient'] }}@endif</div>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    @if (($quantities[$result['id']] ?? 0) > 0)
                                        <div class="pg-stepper" aria-label="Cantidad de {{ $result['name'] }}">
                                            <button type="button" wire:click="decrementProduct({{ $result['id'] }})" aria-label="Restar">−</button>
                                            <span>{{ $quantities[$result['id']] }}</span>
                                            <button type="button" wire:click="incrementProduct({{ $result['id'] }})" aria-label="Sumar">+</button>
                                        </div>
                                    @else
                                        <button class="pg-add" type="button" wire:click="addProduct({{ $result['id'] }})">Agregar</button>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @elseif (mb_strlen(trim($search)) >= 2)
                    <p class="pg-empty" wire:loading.remove wire:target="search">No hay productos con ese texto en Las Delicias.</p>
                @endif
            </section>

            <aside class="pg-card pg-ticket" id="pedido">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <h2>Su pedido</h2>
                    <span class="pg-meta">{{ $summary['count'] }} {{ $summary['count'] === 1 ? 'ítem' : 'ítems' }}</span>
                </div>
                <div class="pg-ticket-scroll">
                @error('lines') <p class="pg-error">{{ $message }}</p> @enderror

                @if ($lines === [])
                    <div class="pg-empty">Todavía vacío. Agregue productos desde el buscador.</div>
                @else
                    @foreach ($lines as $line)
                        <article class="pg-line" wire:key="line-{{ $line['product_id'] }}">
                            <div>
                                <div class="pg-name">{{ $line['name'] }}</div>
                                <div class="pg-meta">{{ $line['code'] }}</div>
                                <div class="pg-stepper" style="margin-top:8px;" aria-label="Cantidad de {{ $line['name'] }}">
                                    <button type="button" wire:click="decrementProduct({{ $line['product_id'] }})" aria-label="Restar">−</button>
                                    <span>{{ $line['quantity'] }}</span>
                                    <button type="button" wire:click="incrementProduct({{ $line['product_id'] }})" aria-label="Sumar">+</button>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <button class="pg-link" type="button" style="padding:6px 10px;" wire:click="removeLine({{ $loop->index }})">Quitar</button>
                            </div>
                        </article>
                    @endforeach
                @endif

                <div class="pg-section">
                    <label>Presentación</label>
                    <div class="pg-choices">
                        <button type="button" class="pg-choice {{ $isWholesale ? '' : 'is-on' }}" wire:click="$set('isWholesale', false)">Unidades</button>
                        <button type="button" class="pg-choice {{ $isWholesale ? 'is-on' : '' }}" wire:click="$set('isWholesale', true)">Cajas al mayor</button>
                    </div>
                </div>

                <div class="pg-section">
                    <label>Entrega</label>
                    <div class="pg-choices">
                        <button type="button" class="pg-choice {{ $fulfillment === 'delivery' ? 'is-on' : '' }}" wire:click="$set('fulfillment', 'delivery')">Delivery</button>
                        <button type="button" class="pg-choice {{ $fulfillment === 'pickup' ? 'is-on' : '' }}" wire:click="$set('fulfillment', 'pickup')">Retiro en punto</button>
                    </div>
                </div>

                <div class="pg-section">
                    <h2 style="margin-bottom:10px;">{{ $fulfillment === 'pickup' ? 'Quién retira' : 'Quién recibe' }}</h2>
                    <div class="pg-grid pg-grid-2">
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
                        <div style="margin-top:12px;">
                            <label for="address">Dirección</label>
                            <input id="address" wire:model="address" placeholder="Calle, edificio, apartamento">
                            @error('address') <p class="pg-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div class="pg-grid pg-grid-2" style="margin-top:12px;">
                        <div>
                            <label for="state">Estado</label>
                            <input id="state" wire:model="state">
                        </div>
                        <div>
                            <label for="notes">Indicaciones</label>
                            <input id="notes" wire:model="deliveryNotes" placeholder="Horario, portería, referencia">
                        </div>
                    </div>
                </div>

                </div>
                <div class="pg-ticket-foot">
                @error('fulfillment') <p class="pg-error">{{ $message }}</p> @enderror
                <button class="pg-btn pg-btn-block" type="button" wire:click="place" wire:loading.attr="disabled" wire:target="place">
                    <span wire:loading.remove wire:target="place">Confirmar pedido</span>
                    <span wire:loading wire:target="place">Registrando…</span>
                </button>
                </div>
            </aside>
        </div>

        <a class="pg-dock" href="#pedido">
            <span>{{ $summary['count'] }} {{ $summary['count'] === 1 ? 'producto' : 'productos' }}</span>
            <strong>Ver pedido</strong>
        </a>
    @endif

    @if ($step === 'done')
        <section class="pg-card pg-gate">
            <div class="pg-check" aria-hidden="true">✓</div>
            <h1 style="margin-top:16px;">Pedido registrado</h1>
            <p class="pg-muted">Quedó <strong>pendiente</strong> para {{ $companyName }}, sucursal Farmadoc Las Delicias.</p>
            <p style="font-size:32px;font-weight:800;letter-spacing:-0.03em;margin:8px 0 0;">{{ $orderNumber }}</p>
            <div class="pg-actions">
                @if ($pdfUrl)
                    <a class="pg-btn" href="{{ $pdfUrl }}">Descargar PDF</a>
                @endif
                <button class="pg-btn-ghost" type="button" wire:click="another">Otro pedido</button>
                <button class="pg-link" type="button" wire:click="leave">Salir</button>
            </div>
            <p class="pg-meta">El PDF trae el detalle comercial. El enlace sirve durante 48 horas.</p>
        </section>
    @endif
</div>
