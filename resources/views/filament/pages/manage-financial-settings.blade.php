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

        @php
            $canEditVatRetentionAgent = $this->canEditVatRetentionAgent();
            $dateInputClass = 'fi-input block w-full rounded-lg border border-gray-950/10 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/5 transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:bg-gray-50 disabled:text-gray-500 disabled:opacity-70 dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:border-primary-400 dark:disabled:bg-transparent';
        @endphp

        <x-filament::section>
            <x-slot name="heading">
                Agente de retención de IVA
            </x-slot>
            <x-slot name="description">
                Cuando está desactivado, las facturas de proveedor cuya <strong>fecha de factura</strong> esté en el período sin
                retención se registran con <strong>0 % de retención</strong>: no generan comprobante y en cuentas por pagar se paga el
                <strong>100 % de la factura</strong>. No se toma en cuenta la fecha de registro en el sistema.
                @unless ($canEditVatRetentionAgent)
                    <br><em>Solo el administrador del sistema puede cambiar esta opción.</em>
                @endunless
            </x-slot>

            <div class="space-y-4">
                <label class="flex items-center gap-3">
                    <input
                        type="checkbox"
                        wire:model.live="vatRetentionAgentActive"
                        @disabled(! $canEditVatRetentionAgent)
                        class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 disabled:opacity-70 dark:border-white/10 dark:bg-white/5"
                    />
                    <span class="text-sm font-medium text-gray-950 dark:text-white">
                        Somos agente de retención de IVA
                    </span>
                </label>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-950 dark:text-white" for="vatRetentionSuspendedFrom">
                        Sin retención desde (fecha de factura)
                    </label>
                    <input
                        id="vatRetentionSuspendedFrom"
                        type="date"
                        wire:model="vatRetentionSuspendedFrom"
                        @disabled(! $canEditVatRetentionAgent)
                        class="{{ $dateInputClass }}"
                    />
                    @error('vatRetentionSuspendedFrom')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>

                @if ($vatRetentionAgentActive && filled($vatRetentionSuspendedFrom))
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-950 dark:text-white" for="vatRetentionResumedFrom">
                            Retener de nuevo desde (fecha de factura)
                        </label>
                        <input
                            id="vatRetentionResumedFrom"
                            type="date"
                            wire:model="vatRetentionResumedFrom"
                            @disabled(! $canEditVatRetentionAgent)
                            class="{{ $dateInputClass }}"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Las facturas entre «Sin retención desde» y el día anterior a esta fecha se mantienen sin retención.
                            Para no tener ningún período sin retención, deje ambas fechas vacías.
                        </p>
                        @error('vatRetentionResumedFrom')
                            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>
        </x-filament::section>

        <div class="flex flex-wrap gap-2">
            <x-filament::button type="submit" color="primary">
                Guardar cambios
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
