<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Política de márgenes — {{ $this->getRecord()->name }}
            </x-slot>
            <x-slot name="description">
                Cada categoría puede tener un porcentaje distinto en esta sucursal. Use «Copiar de otra sucursal» o
                «Ajuste masivo» en la barra superior para cambios en bloque. El valor «Referencia» es el margen por
                defecto de la categoría (solo orientativo).
            </x-slot>

            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <th class="px-4 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white">Categoría</th>
                            <th class="px-4 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white">Referencia (%)</th>
                            <th class="px-4 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white">Margen sucursal (%)</th>
                            <th class="px-4 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($margins as $index => $margin)
                            <tr wire:key="margin-row-{{ $margin['product_category_id'] }}">
                                <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">
                                    {{ $margin['category_name'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ number_format((float) $margin['default_profit_percentage'], 2, '.', ',') }} %
                                </td>
                                <td class="px-4 py-3">
                                    <input
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        max="9999.9999"
                                        wire:model="margins.{{ $index }}.profit_percentage"
                                        class="fi-input block w-full max-w-[10rem] rounded-lg border border-gray-950/10 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/5 transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-white/10"
                                    />
                                    @error('margins.'.$index.'.profit_percentage')
                                        <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($margin['is_active'])
                                        <span class="inline-flex items-center rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400">
                                            Activa
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-500/20 dark:bg-white/5 dark:text-gray-300">
                                            Inactiva
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <div class="flex flex-wrap gap-2">
            <x-filament::button type="submit" color="primary">
                Guardar márgenes
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
