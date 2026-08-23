<div class="sh-account-block">
    <div class="sh-account-block__head">
        <div>
            <p class="sh-sheet__label" style="margin:0;">Direcciones de envío</p>
            <p class="sh-sub" style="margin:0.2rem 0 0;">Elige una principal. En el pedido la usamos primero.</p>
        </div>
    </div>

    @if ($addresses->isNotEmpty())
        <div class="sh-form" style="margin-top:0.75rem;">
            @foreach ($addresses as $address)
                <article class="sh-addr" wire:key="addr-{{ $address->id }}">
                    <span class="sh-option__icon" aria-hidden="true">
                        @include('shop.partials.icon', ['icon' => 'pin'])
                    </span>

                    <div class="sh-addr__copy">
                        <strong>
                            {{ $address->title() }}
                            @if ($address->is_primary)
                                <span class="sh-pill sh-pill--ok">Principal</span>
                            @endif
                        </strong>
                        <span>{{ $address->summary() }}</span>
                        @if (filled($address->reference))
                            <span>{{ $address->reference }}</span>
                        @endif
                    </div>

                    <div class="sh-addr__actions">
                        @unless ($address->is_primary)
                            <button type="button" class="sh-addr__link" wire:click="markPrimary({{ $address->id }})">
                                Principal
                            </button>
                        @endunless
                        <button type="button" class="sh-addr__link" wire:click="startEdit({{ $address->id }})">
                            Editar
                        </button>
                        <button type="button" class="sh-addr__link sh-addr__link--danger" wire:click="confirmDelete({{ $address->id }})">
                            Borrar
                        </button>
                    </div>

                    @if ($confirmingDeleteId === $address->id)
                        <div class="sh-addr__confirm">
                            <p>¿Borrar esta dirección?</p>
                            <div>
                                <button type="button" class="sh-confirm-btn sh-confirm-btn--danger" wire:click="deleteConfirmed">
                                    Sí, borrar
                                </button>
                                <button type="button" class="sh-confirm-btn sh-confirm-btn--quiet" wire:click="cancelDelete">
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @else
        <p class="sh-sub" style="margin-top:0.7rem;">Aún no tienes direcciones. Agrega la de tu casa o donde sueles recibir.</p>
    @endif

    @if ($composing)
        <form class="sh-form sh-addr-form" wire:submit="save">
            <p class="sh-sheet__label">{{ $editingId ? 'Editar dirección' : 'Nueva dirección' }}</p>

            <div class="sh-field">
                <label class="sh-field__label" for="ad-label">Nombre (opcional)</label>
                <input
                    id="ad-label"
                    type="text"
                    size="1"
                    wire:model="label"
                    class="sh-input"
                    placeholder="Casa, trabajo, familia…"
                >
            </div>

            <div class="sh-field">
                <label class="sh-field__label" for="ad-line">Dirección</label>
                <textarea
                    id="ad-line"
                    wire:model="line"
                    @class(['sh-textarea', 'is-invalid' => $errors->has('line')])
                    rows="3"
                    placeholder="Calle, edificio o casa"
                ></textarea>
                @error('line')
                    <p class="sh-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="sh-form__split">
                <div class="sh-field">
                    <label class="sh-field__label" for="ad-city">Ciudad</label>
                    <input
                        id="ad-city"
                        type="text"
                        size="1"
                        wire:model="city"
                        @class(['sh-input', 'is-invalid' => $errors->has('city')])
                        placeholder="Barinas"
                        autocomplete="address-level2"
                    >
                    @error('city')
                        <p class="sh-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sh-field">
                    <label class="sh-field__label" for="ad-state">Estado</label>
                    <input
                        id="ad-state"
                        type="text"
                        size="1"
                        wire:model="state"
                        @class(['sh-input', 'is-invalid' => $errors->has('state')])
                        placeholder="Barinas"
                        autocomplete="address-level1"
                    >
                    @error('state')
                        <p class="sh-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="sh-field">
                <label class="sh-field__label" for="ad-reference">Referencia (opcional)</label>
                <input
                    id="ad-reference"
                    type="text"
                    size="1"
                    wire:model="reference"
                    class="sh-input"
                    placeholder="Piso, apartamento, horario"
                >
            </div>

            <label class="sh-check">
                <input type="checkbox" wire:model="isPrimary">
                <span>Usar como dirección principal</span>
            </label>

            <div class="sh-addr-form__actions">
                <button type="submit" class="sh-btn sh-btn--primary" wire:loading.attr="disabled" wire:target="save">
                    {{ $editingId ? 'Guardar cambios' : 'Guardar dirección' }}
                </button>
                <button type="button" class="sh-btn sh-btn--ghost" wire:click="cancelForm">
                    Cancelar
                </button>
            </div>
        </form>
    @else
        <button type="button" class="sh-btn sh-btn--ghost sh-btn--block" style="margin-top:0.85rem;" wire:click="startCreate">
            @include('shop.partials.icon', ['icon' => 'plus'])
            Agregar dirección
        </button>
    @endif
</div>
