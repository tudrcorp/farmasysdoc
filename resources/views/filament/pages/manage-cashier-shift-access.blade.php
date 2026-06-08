<x-filament-panels::page>
    <div class="w-full max-w-none space-y-6">
        <x-filament::section>
            <x-slot name="heading">Desbloqueo automático</x-slot>
            <x-slot name="description">
                Si un cajero cierra la caja física, no podrá ingresar hasta las <strong>{{ $dailyUnlockTimeLabel }}</strong> del día siguiente (hora local de la aplicación), salvo que usted lo habilite manualmente desde esta pantalla.
            </x-slot>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Cajeros</x-slot>
            <x-slot name="description">
                Solo se muestran usuarios con rol CAJERO. Use «Habilitar acceso» para permitir el ingreso inmediato fuera del horario programado.
            </x-slot>

            <div class="mb-4 flex justify-end">
                <x-filament::button color="gray" wire:click="refreshCashiers">
                    Actualizar
                </x-filament::button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cajero</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Sucursal</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Correo</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Caja física</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Estado ingreso</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Bloqueado hasta</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Último cierre</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-transparent">
                        @forelse ($cashiers as $cashier)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-900 dark:text-white">{{ $cashier['name'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $cashier['branch_name'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $cashier['email'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    @if ($cashier['box_is_open'])
                                        <span class="inline-flex items-center rounded-full bg-success-600/15 px-2 py-1 text-xs font-semibold text-success-700 dark:bg-success-500/20 dark:text-success-300">
                                            Abierta
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-500/15 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-400/20 dark:text-gray-300">
                                            Cerrada
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    @if ($cashier['is_locked'])
                                        <span class="inline-flex items-center rounded-full bg-danger-600/15 px-2 py-1 text-xs font-semibold text-danger-700 dark:bg-danger-500/20 dark:text-danger-300">
                                            Bloqueado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-success-600/15 px-2 py-1 text-xs font-semibold text-success-700 dark:bg-success-500/20 dark:text-success-300">
                                            Puede ingresar
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $cashier['locked_until'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $cashier['last_closed_at'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right">
                                    @if ($cashier['is_locked'])
                                        <x-filament::button
                                            size="sm"
                                            color="success"
                                            wire:click="grantCashierAccess({{ $cashier['user_id'] }})"
                                            wire:confirm="¿Habilitar el ingreso inmediato de {{ $cashier['name'] }}?"
                                        >
                                            Habilitar acceso
                                        </x-filament::button>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No hay usuarios con rol CAJERO registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
