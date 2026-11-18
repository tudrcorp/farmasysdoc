@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\PosHeldSale> $heldSales */
@endphp

<div class="flex flex-col gap-3">
    @forelse ($heldSales as $heldSale)
        @php
            $minutes = $heldSale->minutesPaused();
            $isWhatsapp = $heldSale->isFromWhatsapp();
            $isOverdue = ! $isWhatsapp && $minutes >= $reminderMinutes;
            $isOwn = (int) $heldSale->user_id === $viewerId;
            $viewer = auth()->user();
            $canResume = $canUseRegister && $viewer instanceof \App\Models\User && app(\App\Services\Sales\PosHeldSaleService::class)->canResume($viewer, $heldSale);
            $lines = is_array($heldSale->item_lines) ? $heldSale->item_lines : [];
        @endphp

        <div
            wire:key="pos-held-sale-{{ $heldSale->getKey() }}"
            @class([
                'rounded-xl border p-3',
                'border-danger-500/50 bg-danger-500/5' => $isOverdue,
                'border-gray-200 dark:border-white/10' => ! $isOverdue,
            ])
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-base font-semibold text-gray-950 dark:text-white">
                        @if ($isWhatsapp)
                            <span class="mr-1 inline-flex items-center rounded-md bg-success-500/15 px-1.5 py-0.5 align-middle text-xs font-semibold text-success-700 dark:text-success-300">WhatsApp</span>
                        @endif
                        {{ $heldSale->label ?? 'Mostrador' }}
                        @if (filled($heldSale->note))
                            <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $heldSale->note }}</span>
                        @endif
                    </p>

                    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                        {{ $heldSale->items_count }} {{ $heldSale->items_count === 1 ? 'producto' : 'productos' }}
                        @if ($heldSale->estimated_total_usd !== null)
                            · aprox. ${{ number_format((float) $heldSale->estimated_total_usd, 2, ',', '.') }}
                        @endif
                        · en pausa {{ $minutes < 1 ? 'hace menos de 1 min' : 'hace '.$minutes.' min' }}
                    </p>

                    @unless ($isOwn)
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ $isWhatsapp ? 'Pedido por WhatsApp' : 'Cajero: '.($heldSale->user?->name ?? '—') }}
                            @if ($heldSale->branch)
                                · {{ $heldSale->branch->name }}
                            @endif
                        </p>
                    @endunless

                    @if ($lines !== [])
                        <ul class="mt-2 list-inside list-disc text-xs text-gray-600 dark:text-gray-400">
                            @foreach (array_slice($lines, 0, 5) as $line)
                                <li class="truncate">{{ $line }}</li>
                            @endforeach
                            @if (count($lines) > 5)
                                <li>y {{ count($lines) - 5 }} más…</li>
                            @endif
                        </ul>
                    @endif

                    @if ($isOverdue)
                        <p class="mt-2 text-xs font-medium text-danger-600 dark:text-danger-400">
                            Lleva más de {{ $reminderMinutes }} minutos en pausa: cóbrela o elimínela.
                        </p>
                    @endif
                </div>

                <div class="flex shrink-0 gap-2">
                    @if ($canResume)
                        <x-filament::button
                            size="sm"
                            color="success"
                            icon="heroicon-m-play"
                            wire:click="mountAction('{{ $resumeActionName }}', { heldSaleId: {{ (int) $heldSale->getKey() }} })"
                        >
                            Retomar
                        </x-filament::button>
                    @endif

                    <x-filament::button
                        size="sm"
                        color="danger"
                        outlined
                        icon="heroicon-m-trash"
                        wire:click="mountAction('{{ $discardActionName }}', { heldSaleId: {{ (int) $heldSale->getKey() }} })"
                    >
                        Eliminar
                    </x-filament::button>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/15 dark:text-gray-400">
            No hay ventas en pausa.
        </div>
    @endforelse
</div>
