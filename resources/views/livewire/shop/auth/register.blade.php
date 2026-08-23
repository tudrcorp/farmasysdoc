<div class="sh-gate">
    <div class="sh-gate__pad">
        <header class="sh-gate__top">
            <a href="{{ route('shop.home') }}" wire:navigate class="sh-gate__back" aria-label="Volver">
                @include('shop.partials.icon', ['icon' => 'back'])
            </a>
            <div class="sh-gate__titles">
                <strong>Crear cuenta</strong>
                <span>Tus datos</span>
            </div>
            <span class="sh-gate__step">1/2</span>
        </header>

        <div class="sh-gate__copy">
            <h1>¿Cómo te llamas?</h1>
            <p>Usá tu cédula o tu celular. Si preferís Google, volvé y tocá el botón de Google.</p>
        </div>

        <form class="sh-gate__form" wire:submit="continue">
            <label class="sh-gate__field">
                <span>Nombre</span>
                <span class="sh-gate__box">
                    <input type="text" size="1" wire:model="firstName" autocomplete="given-name" placeholder="María">
                </span>
                @error('firstName') <span class="sh-gate__error">{{ $message }}</span> @enderror
            </label>

            <label class="sh-gate__field">
                <span>Apellido</span>
                <span class="sh-gate__box">
                    <input type="text" size="1" wire:model="lastName" autocomplete="family-name" placeholder="Pérez">
                </span>
                @error('lastName') <span class="sh-gate__error">{{ $message }}</span> @enderror
            </label>

            <div class="sh-gate__tabs" role="tablist" aria-label="Cómo te registras" data-method="{{ $method }}">
                <button
                    type="button"
                    class="sh-gate__tab"
                    data-method="document"
                    @class(['is-on' => $method === 'document'])
                    wire:click="selectMethod('document')"
                    role="tab"
                    aria-selected="{{ $method === 'document' ? 'true' : 'false' }}"
                >
                    Cédula
                </button>
                <button
                    type="button"
                    class="sh-gate__tab"
                    data-method="phone"
                    @class(['is-on' => $method === 'phone'])
                    wire:click="selectMethod('phone')"
                    role="tab"
                    aria-selected="{{ $method === 'phone' ? 'true' : 'false' }}"
                >
                    Nro. de teléfono
                </button>
            </div>

            @if ($method === 'document')
                <label class="sh-gate__field">
                    <span>Cédula de identidad</span>
                    <div class="sh-gate__id">
                        <span class="sh-gate__box">
                            <select wire:model="documentType" aria-label="Nacionalidad">
                                <option value="V">V</option>
                                <option value="E">E</option>
                            </select>
                        </span>
                        <span class="sh-gate__box">
                            <input type="text" size="1" inputmode="numeric" autocomplete="off" wire:model="documentNumber" placeholder="12345678">
                        </span>
                    </div>
                    @error('documentType') <span class="sh-gate__error">{{ $message }}</span> @enderror
                    @error('documentNumber') <span class="sh-gate__error">{{ $message }}</span> @enderror
                </label>
            @else
                <label class="sh-gate__field">
                    <span>Nro. de teléfono</span>
                    <span class="sh-gate__box">
                        <input type="tel" size="1" inputmode="tel" autocomplete="tel" wire:model="phone" placeholder="0412-1234567">
                    </span>
                    @error('phone') <span class="sh-gate__error">{{ $message }}</span> @enderror
                </label>
            @endif

            <button type="submit" class="sh-gate__submit" wire:loading.attr="disabled">
                Continuar
            </button>
        </form>
    </div>
</div>
