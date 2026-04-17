<x-filament-panels::page>
    <form wire:submit.prevent="save" class="max-w-md space-y-8">
        <x-filament::section>
            <x-slot name="heading">
                IVA por defecto
            </x-slot>
            <x-slot name="description">
                Porcentaje aplicado a productos con «Grava IVA» en <strong>pedidos</strong>, <strong>compras</strong> y
                <strong>caja (ventas)</strong>. Las líneas ya guardadas conservan su tasa; al crear líneas nuevas se usa este valor.
            </x-slot>

            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-950 dark:text-white" for="defaultVatRatePercent">
                    Tasa de IVA (%)
                </label>
                <input
                    id="defaultVatRatePercent"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    wire:model="defaultVatRatePercent"
                    class="fi-input block w-full rounded-lg border border-gray-950/10 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/5 transition placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:bg-gray-50 disabled:text-gray-500 disabled:opacity-70 dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:placeholder:text-gray-500 dark:focus:border-primary-400 dark:disabled:bg-transparent"
                />
                @error('defaultVatRatePercent')
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                IGTF (efectivo en dólares)
            </x-slot>
            <x-slot name="description">
                Porcentaje aplicado al <strong>total de la factura</strong> (subtotal − descuento + IVA) cuando el cobro en caja es
                <strong>Efectivo USD</strong>. No aplica a transferencia, Zelle, bolívares ni pago mixto.
            </x-slot>

            <div class="space-y-2">
                <label class="text-sm font-medium text-gray-950 dark:text-white" for="igtfRatePercent">
                    Tasa IGTF (%)
                </label>
                <input
                    id="igtfRatePercent"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    wire:model="igtfRatePercent"
                    class="fi-input block w-full rounded-lg border border-gray-950/10 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/5 transition placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:bg-gray-50 disabled:text-gray-500 disabled:opacity-70 dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:placeholder:text-gray-500 dark:focus:border-primary-400 dark:disabled:bg-transparent"
                />
                @error('igtfRatePercent')
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
