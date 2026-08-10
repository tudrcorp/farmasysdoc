@props([
    'idPrefix' => 'bdv',
    'compact' => false,
])

<div @class([
    'grid gap-4',
    'sm:grid-cols-2' => ! $compact,
])>
    @if ($showBranchSelect)
        <div @class(['space-y-1', 'sm:col-span-2' => ! $compact])>
            <label class="farmadoc-bdv-pm-field-label" for="{{ $idPrefix }}-branch">Sucursal</label>
            <select
                id="{{ $idPrefix }}-branch"
                wire:model.live="branchId"
                class="farmadoc-bdv-pm-field-input fi-select-input"
            >
                @foreach ($branchOptions as $branch)
                    <option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>
                @endforeach
            </select>
            @error('branchId')
                <p class="farmadoc-bdv-pm-field-error">{{ $message }}</p>
            @enderror
        </div>
    @endif

    <div @class(['space-y-1', 'sm:col-span-2' => ! $compact])>
        <label class="farmadoc-bdv-pm-field-label flex cursor-pointer items-center gap-2">
            <input type="checkbox" wire:model="reqCed" class="rounded border-gray-300 text-primary-600 dark:border-white/20" />
            <span>Validar cédula (reqCed) — solo pagos BDV a BDV</span>
        </label>
    </div>

    @include('filament.pages.partials.bdv-input', [
        'name' => 'cedulaPagador',
        'label' => 'Cédula pagador',
        'placeholder' => 'V12345678',
        'class' => $compact ? '' : '',
        'idPrefix' => $idPrefix,
    ])
    @include('filament.pages.partials.bdv-input', [
        'name' => 'telefonoPagador',
        'label' => 'Teléfono pagador',
        'placeholder' => '04141234567',
        'idPrefix' => $idPrefix,
    ])
    @include('filament.pages.partials.bdv-input', [
        'name' => 'referencia',
        'label' => 'Referencia',
        'placeholder' => '12345678',
        'idPrefix' => $idPrefix,
    ])

    <div class="space-y-1">
        <label class="farmadoc-bdv-pm-field-label" for="{{ $idPrefix }}-fecha">Fecha pago</label>
        <input
            id="{{ $idPrefix }}-fecha"
            type="date"
            wire:model="fechaPago"
            class="farmadoc-bdv-pm-field-input fi-input"
        />
        @error('fechaPago')
            <p class="farmadoc-bdv-pm-field-error">{{ $message }}</p>
        @enderror
    </div>

    @include('filament.pages.partials.bdv-input', [
        'name' => 'importe',
        'label' => 'Importe (Bs.)',
        'placeholder' => '120.00',
        'idPrefix' => $idPrefix,
    ])

    <div class="space-y-1">
        <label class="farmadoc-bdv-pm-field-label" for="{{ $idPrefix }}-bancoOrigen">Banco origen</label>
        <select
            id="{{ $idPrefix }}-bancoOrigen"
            wire:model="bancoOrigen"
            class="farmadoc-bdv-pm-field-input fi-select-input"
        >
            @foreach (\App\Enums\VenezuelanPagoMovilBank::optionsForSelect() as $code => $label)
                <option value="{{ $code }}">{{ $label }}</option>
            @endforeach
        </select>
        @error('bancoOrigen')
            <p class="farmadoc-bdv-pm-field-error">{{ $message }}</p>
        @enderror
    </div>
</div>
