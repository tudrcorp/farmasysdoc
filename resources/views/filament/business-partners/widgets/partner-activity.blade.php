@php
    $money = fn (mixed $value): string => '$'.number_format((float) $value, 2, ',', '.');
    $statusChip = [
        'pendiente' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-400/30',
        'en-proceso' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400 dark:ring-info-400/30',
        'finalizado' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/30',
        'consumo' => 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-400/30',
        'consumo-anulado' => 'bg-gray-50 text-gray-600 ring-gray-600/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10',
    ];
    $maxQuantity = max(1, (float) collect($topProducts)->max('quantity'));
@endphp

<x-filament-widgets::widget>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section class="dale-card px-5 py-4" aria-labelledby="dale-top-products">
            <header class="mb-3">
                <h2 id="dale-top-products" class="text-base font-semibold text-gray-950 dark:text-white">Productos más solicitados</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Últimos {{ $days }} días, por cantidad (pedidos y consumos por convenio).</p>
            </header>
            @forelse ($topProducts as $index => $product)
                <div class="py-2">
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <p class="min-w-0 truncate"><span class="me-1.5 font-semibold text-gray-400">{{ $index + 1 }}.</span><span class="font-medium text-gray-900 dark:text-gray-100">{{ $product['name'] }}</span></p>
                        <p class="dale-figures shrink-0 text-gray-600 dark:text-gray-300"><span class="font-semibold text-gray-950 dark:text-white">{{ number_format($product['quantity'], 0, ',', '.') }}</span> und · {{ $money($product['amount']) }}</p>
                    </div>
                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="dale-progress-bar h-full rounded-full bg-primary-500" style="width: {{ (int) round($product['quantity'] / $maxQuantity * 100) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Aún no hay movimientos en este período.</p>
            @endforelse
        </section>

        <section class="dale-card px-5 py-4" aria-labelledby="dale-recent-orders">
            <header class="mb-2 flex items-center justify-between gap-3">
                <div>
                    <h2 id="dale-recent-orders" class="text-base font-semibold text-gray-950 dark:text-white">Últimos movimientos</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Pedidos y consumos por convenio más recientes.</p>
                </div>
                @if ($consumptionsUrl ?? $ordersUrl)
                    <a href="{{ $consumptionsUrl ?? $ordersUrl }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                        Ver todos
                        <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5" />
                    </a>
                @endif
            </header>
            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($recentOrders as $order)
                    <li>
                        @php
                            $rowClass = 'dale-row-link -mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-2.5';
                        @endphp
                        @if ($order['url'])
                            <a href="{{ $order['url'] }}" wire:navigate class="{{ $rowClass }} hover:bg-gray-50 dark:hover:bg-white/5">
                                @include('filament.business-partners.widgets.partials.recent-order-row', ['order' => $order, 'money' => $money, 'statusChip' => $statusChip])
                            </a>
                        @else
                            <div class="{{ $rowClass }}">
                                @include('filament.business-partners.widgets.partials.recent-order-row', ['order' => $order, 'money' => $money, 'statusChip' => $statusChip])
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Todavía no hay movimientos.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-filament-widgets::widget>
