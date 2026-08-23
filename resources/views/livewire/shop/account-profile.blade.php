<div class="sh-account-block">
    <p class="sh-sheet__label" style="margin-top:0;">Tus datos</p>
    <p class="sh-sub" style="margin:-0.15rem 0 0.75rem;">Los usamos para tus pedidos y la factura.</p>

    <form class="sh-form" wire:submit="save">
        <div class="sh-form__split">
            <div class="sh-field">
                <label class="sh-field__label" for="ac-first-name">Nombre</label>
                <input
                    id="ac-first-name"
                    type="text"
                    size="1"
                    wire:model="firstName"
                    @class(['sh-input', 'is-invalid' => $errors->has('firstName')])
                    autocomplete="given-name"
                    placeholder="María"
                >
                @error('firstName')
                    <p class="sh-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="sh-field">
                <label class="sh-field__label" for="ac-last-name">Apellido</label>
                <input
                    id="ac-last-name"
                    type="text"
                    size="1"
                    wire:model="lastName"
                    @class(['sh-input', 'is-invalid' => $errors->has('lastName')])
                    autocomplete="family-name"
                    placeholder="Pérez"
                >
                @error('lastName')
                    <p class="sh-error">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="sh-field">
            <label class="sh-field__label" for="ac-document">Cédula de identidad</label>
            <div class="sh-form__row">
                <select id="ac-document-type" wire:model="documentType" class="sh-select" aria-label="Nacionalidad">
                    <option value="V">V</option>
                    <option value="E">E</option>
                </select>
                <input
                    id="ac-document"
                    type="text"
                    size="1"
                    wire:model="documentNumber"
                    @class(['sh-input', 'is-invalid' => $errors->has('documentNumber')])
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="12345678"
                >
            </div>
            @error('documentNumber')
                <p class="sh-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="sh-field">
            <label class="sh-field__label" for="ac-phone">Teléfono</label>
            <input
                id="ac-phone"
                type="tel"
                size="1"
                wire:model="phone"
                @class(['sh-input', 'is-invalid' => $errors->has('phone')])
                inputmode="tel"
                autocomplete="tel"
                placeholder="0412-1234567"
            >
            @error('phone')
                <p class="sh-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="sh-btn sh-btn--primary sh-btn--block" wire:loading.attr="disabled" wire:target="save">
            Guardar datos
        </button>
    </form>
</div>
