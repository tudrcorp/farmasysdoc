<x-filament-panels::page>
    <div class="w-full max-w-none space-y-8">
        <div wire:poll.8s="refreshCashBoxSnapshot" class="space-y-8">
        <x-filament::section>
            @if ($isCashierView)
                <x-slot name="heading">
                    Estado de su caja
                </x-slot>
                <x-slot name="description">
                    Caja física es el efectivo que tiene a la mano para dar vueltos. Esta vista se actualiza automáticamente para conciliar su turno.
                </x-slot>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-950/10 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Turno</dt>
                        <dd class="mt-1">
                            @if ($boxIsOpen)
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-success-600/15 px-2.5 py-1 text-sm font-semibold text-success-700 dark:bg-success-500/20 dark:text-success-300"
                                >
                                    <span class="size-2 rounded-full bg-success-500"></span>
                                    Abierta
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-gray-500/15 px-2.5 py-1 text-sm font-semibold text-gray-700 dark:bg-gray-400/20 dark:text-gray-300"
                                >
                                    <span class="size-2 rounded-full bg-gray-400"></span>
                                    Cerrada
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div class="rounded-xl border border-gray-950/10 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Montos actuales (declarados)</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                            USD {{ number_format((float) $boxAmountUsd, 2) }}
                            <span class="text-gray-400">·</span>
                            VES {{ number_format((float) $boxAmountVes, 2, ',', '.') }}
                        </dd>
                    </div>
                    @if (filled($boxOpenedAtForView))
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Última apertura</dt>
                            <dd class="mt-0.5 text-sm text-gray-950 dark:text-white">
                                {{ $boxOpenedAtForView }}
                            </dd>
                        </div>
                    @endif
                    @if (filled($boxClosedAtForView))
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Último cierre</dt>
                            <dd class="mt-0.5 text-sm text-gray-950 dark:text-white">
                                {{ $boxClosedAtForView }}
                            </dd>
                        </div>
                    @endif
                </dl>
            @else
                <x-slot name="heading">
                    Aperturas y saldos de cajas por sucursal
                </x-slot>
                <x-slot name="description">
                    Monitoreo en vivo para administración y gerencia según sucursales asignadas.
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Sucursal</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cajero</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Turno</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Saldo USD</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Saldo VES</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Apertura</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cierre</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Movimientos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-transparent">
                            @forelse ($branchCashBoxes as $boxRow)
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $boxRow['branch_name'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $boxRow['cashier_name'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2">
                                        @if ($boxRow['is_open'])
                                            <span class="inline-flex items-center rounded-full bg-success-600/15 px-2 py-1 text-xs font-semibold text-success-700 dark:bg-success-500/20 dark:text-success-300">
                                                Abierta
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-500/15 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-400/20 dark:text-gray-300">
                                                Cerrada
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right text-gray-700 dark:text-gray-200">${{ number_format((float) $boxRow['amount_usd'], 2) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right text-gray-700 dark:text-gray-200">Bs. {{ number_format((float) $boxRow['amount_ves'], 2, ',', '.') }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $boxRow['opened_at'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $boxRow['closed_at'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ (int) $boxRow['movements_count'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No hay cajas visibles para su alcance de sucursales.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
        </div>

        @if ($isCashierView && ! $boxIsOpen)
            <form wire:submit.prevent="openCashBox" class="space-y-6">
                <x-filament::section>
                    <x-slot name="heading">Apertura de caja</x-slot>
                    <x-slot name="description">
                        Al iniciar su turno, declare cuánto efectivo tiene en la caja física (vueltos) en cada moneda.
                    </x-slot>

                    <div class="space-y-6">
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">Efectivo USD — conteo por denominación</h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Indique cuántos billetes hay en caja por cada denominación. El total se calcula automáticamente.
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($this->openUsdBillBreakdown() as $row)
                                    <div @class([
                                        'rounded-xl border p-3',
                                        'border-gray-950/10 bg-white dark:border-white/10 dark:bg-white/5',
                                    ])>
                                        <label
                                            class="text-sm font-medium text-gray-950 dark:text-white"
                                            for="openUsdBill{{ $row['denomination'] }}"
                                        >
                                            US${{ $row['denomination'] }}
                                        </label>
                                        <input
                                            id="openUsdBill{{ $row['denomination'] }}"
                                            type="number"
                                            min="0"
                                            step="1"
                                            inputmode="numeric"
                                            wire:model.live="openUsdBillCounts.{{ $row['denomination'] }}"
                                            class="fi-input mt-2 block w-full rounded-lg border border-gray-950/10 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/5 transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:border-primary-400"
                                            autocomplete="off"
                                        />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Subtotal: US$ {{ number_format($row['subtotal'], 2) }}
                                        </p>
                                        @error('openUsdBillCounts.'.$row['denomination'])
                                            <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>

                            <div class="rounded-xl border border-primary-500/20 bg-primary-500/5 px-4 py-3 dark:border-primary-400/20 dark:bg-primary-400/10">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Total apertura USD</span>
                                    <span class="text-lg font-semibold text-primary-600 dark:text-primary-400">
                                        US$ {{ number_format((float) $openUsd, 2) }}
                                    </span>
                                </div>
                            </div>

                            @error('openUsd')
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2 sm:max-w-md">
                            <label class="text-sm font-medium text-gray-950 dark:text-white" for="openVes">Monto inicial VES</label>
                            <input
                                id="openVes"
                                type="text"
                                inputmode="decimal"
                                wire:model="openVes"
                                class="fi-input block w-full rounded-lg border border-gray-950/10 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/5 transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:border-primary-400"
                                autocomplete="off"
                            />
                            @error('openVes')
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </x-filament::section>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button type="submit" color="success">
                        Abrir caja física
                    </x-filament::button>
                </div>
            </form>
        @elseif ($isCashierView)
            <x-filament::section>
                <x-slot name="heading">Conciliación de cierre en tiempo real</x-slot>
                <x-slot name="description">
                    Compare lo esperado por sistema contra lo declarado en cierre para detectar descuadres al instante.
                </x-slot>

                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-xl border border-gray-950/10 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Saldo esperado (sistema)</h4>
                        <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">USD {{ number_format((float) $closeReconciliation['expected_usd'], 2) }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">VES {{ number_format((float) $closeReconciliation['expected_ves'], 2, ',', '.') }}</p>
                    </div>

                    <div class="rounded-xl border border-gray-950/10 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Saldo declarado (cajero)</h4>
                        <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">USD {{ number_format((float) $closeReconciliation['declared_usd'], 2) }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">VES {{ number_format((float) $closeReconciliation['declared_ves'], 2, ',', '.') }}</p>
                    </div>

                    <div class="rounded-xl border p-4 {{ $closeReconciliation['has_mismatch'] ? 'border-danger-500/40 bg-danger-500/10 dark:bg-danger-500/15' : 'border-success-500/40 bg-success-500/10 dark:bg-success-500/15' }}">
                        <h4 class="text-xs font-semibold uppercase tracking-wide {{ $closeReconciliation['has_mismatch'] ? 'text-danger-700 dark:text-danger-300' : 'text-success-700 dark:text-success-300' }}">
                            Diferencia (declarado - esperado)
                        </h4>
                        <p class="mt-2 text-sm font-semibold {{ (float) $closeReconciliation['difference_usd'] === 0.0 ? 'text-gray-800 dark:text-gray-100' : ((float) $closeReconciliation['difference_usd'] > 0 ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300') }}">
                            USD {{ number_format((float) $closeReconciliation['difference_usd'], 2) }}
                        </p>
                        <p class="mt-1 text-sm font-semibold {{ (float) $closeReconciliation['difference_ves'] === 0.0 ? 'text-gray-800 dark:text-gray-100' : ((float) $closeReconciliation['difference_ves'] > 0 ? 'text-success-700 dark:text-success-300' : 'text-danger-700 dark:text-danger-300') }}">
                            VES {{ number_format((float) $closeReconciliation['difference_ves'], 2, ',', '.') }}
                        </p>
                        <p class="mt-2 text-xs {{ $closeReconciliation['has_mismatch'] ? 'text-danger-700 dark:text-danger-300' : 'text-success-700 dark:text-success-300' }}">
                            {{ $closeReconciliation['has_mismatch'] ? 'Hay descuadre: revise billetes, vueltos y conteo físico antes de cerrar.' : 'Conciliado: declarado coincide con el saldo esperado.' }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <div class="space-y-6">
                <x-filament::section>
                    <x-slot name="heading">Cierre de caja</x-slot>
                    <x-slot name="description">
                        Al terminar el turno, declare el efectivo que queda en la caja física. Revise billetes y monedas antes de confirmar.
                    </x-slot>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-gray-950 dark:text-white" for="closeUsd">Monto al cierre USD</label>
                            <input
                                id="closeUsd"
                                type="text"
                                inputmode="decimal"
                                wire:model="closeUsd"
                                class="fi-input block w-full rounded-lg border border-gray-950/10 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/5 transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:border-primary-400"
                                autocomplete="off"
                            />
                            @error('closeUsd')
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-gray-950 dark:text-white" for="closeVes">Monto al cierre VES</label>
                            <input
                                id="closeVes"
                                type="text"
                                inputmode="decimal"
                                wire:model="closeVes"
                                class="fi-input block w-full rounded-lg border border-gray-950/10 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/5 transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:border-primary-400"
                                autocomplete="off"
                            />
                            @error('closeVes')
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </x-filament::section>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button color="gray" wire:click="mountAction('closePhysicalCashBox')">
                        Cerrar caja física
                    </x-filament::button>
                </div>
            </div>

            <x-filament-actions::modals />
        @endif

        <x-filament::section>
            <x-slot name="heading">
                {{ $isCashierView ? 'Movimientos del turno (conciliación en tiempo real)' : 'Movimientos de cajas por sucursal (tiempo real)' }}
            </x-slot>
            <x-slot name="description">
                Billete del cliente entra a caja en USD; USD retirados y VES de vuelto restante salen de caja.
            </x-slot>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Fecha</th>
                            @if (! $isCashierView)
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Sucursal</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cajero</th>
                            @endif
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Venta</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Billete del cliente (USD)</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">USD retirados de la caja para vueltos</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Vuelto en VES (restante)</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Variación USD caja</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Variación VES caja</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-transparent">
                        @forelse ($recentMovements as $movement)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $movement['created_at'] }}</td>
                                @if (! $isCashierView)
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $movement['branch_name'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $movement['cashier_name'] }}</td>
                                @endif
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $movement['sale_number'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-700 dark:text-gray-200">${{ number_format((float) $movement['client_bill_usd'], 2) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-700 dark:text-gray-200">${{ number_format((float) $movement['drawer_out_usd'], 2) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-700 dark:text-gray-200">Bs. {{ number_format((float) $movement['final_change_ves'], 2, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right {{ (float) $movement['usd_delta'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                    ${{ number_format((float) $movement['usd_delta'], 2) }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right {{ (float) $movement['ves_delta'] < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-700 dark:text-gray-200' }}">
                                    Bs. {{ number_format((float) $movement['ves_delta'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isCashierView ? 7 : 9 }}" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Sin movimientos registrados todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
