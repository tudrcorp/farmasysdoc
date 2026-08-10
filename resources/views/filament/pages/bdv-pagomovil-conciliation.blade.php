<x-filament-panels::page>
    @php($bdvOutcomeOk = $lastSuccess)

    <div class="farmadoc-bdv-pm-page space-y-6">
        <x-filament::section>
            <x-slot name="heading">Antes de conciliar</x-slot>
            <x-slot name="description">
                Ingrese los datos exactos del comprobante de Pago Móvil del cliente. El teléfono del comercio se toma
                automáticamente de la sucursal. Entorno activo: <strong>{{ $environmentLabel }}</strong>.
            </x-slot>
            <div class="flex flex-wrap gap-3">
                @if ($canViewHistory)
                    <x-filament::button tag="a" :href="$conciliationsUrl" color="gray" outlined icon="heroicon-o-queue-list">
                        Ver historial de conciliaciones
                    </x-filament::button>
                @endif
            </div>
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-5">
            <div class="xl:col-span-3">
                <x-filament::section
                    heading="Datos del pago"
                    description="POST getMovement/v2 — reqCed solo en pagos BDV → BDV según manual del banco."
                >
                    <form wire:submit.prevent="submitConciliation" class="space-y-4">
                        @include('filament.partials.bdv-pagomovil-conciliation-form-fields', [
                            'idPrefix' => 'bdv',
                            'compact' => false,
                        ])

                        <div class="flex flex-wrap gap-3">
                            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="submitConciliation">
                                <span wire:loading.remove wire:target="submitConciliation">Enviar conciliación</span>
                                <span wire:loading wire:target="submitConciliation">Consultando BDV…</span>
                            </x-filament::button>
                            <x-filament::button type="button" color="gray" outlined wire:click="resetForm">
                                Limpiar formulario
                            </x-filament::button>
                        </div>
                    </form>
                </x-filament::section>
            </div>

            <div class="xl:col-span-2">
                <x-filament::section heading="Resultado">
                    @if ($lastResult === null)
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/80 p-6 text-center dark:border-white/15 dark:bg-white/5">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Complete el formulario y pulse <strong>Enviar conciliación</strong>. Aquí verá la respuesta del banco.
                            </p>
                        </div>
                    @else
                        @include('filament.partials.bdv-pagomovil-conciliation-result', [
                            'lastResult' => $lastResult,
                            'bdvOutcomeOk' => $bdvOutcomeOk,
                        ])
                    @endif
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
