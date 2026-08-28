<x-filament-panels::page>
    <form wire:submit.prevent="save" class="max-w-2xl space-y-8">
        <x-filament::section>
            <x-slot name="heading">
                Dirección de la empresa principal
            </x-slot>
            <x-slot name="description">
                Domicilio fiscal de la razón social (SENIAT). Es independiente de la dirección de cada sucursal
                y aparece en las <strong>facturas fiscales</strong> y en los comprobantes de retención.
            </x-slot>

            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-950 dark:text-white" for="companyFiscalAddress">
                    Dirección fiscal
                </label>
                <textarea
                    id="companyFiscalAddress"
                    rows="4"
                    maxlength="2000"
                    wire:model="address"
                    placeholder="Avenida, casa/local, urbanización, sector, ciudad y estado"
                    class="fi-input block w-full rounded-lg border border-gray-950/10 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/5 transition placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:bg-gray-50 disabled:text-gray-500 disabled:opacity-70 dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:placeholder:text-gray-500 dark:focus:border-primary-400 dark:disabled:bg-transparent"
                ></textarea>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Si deja el campo vacío, se usará la dirección configurada en el sistema como respaldo.
                </p>
                @error('address')
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
            </div>
        </x-filament::section>

        <div class="flex flex-wrap gap-2">
            <x-filament::button type="submit" color="primary">
                Guardar cambios
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
