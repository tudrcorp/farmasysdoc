@php
    $pollingInterval = $pollingInterval ?? null;
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
    "
>
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Medicamentos más gestionados</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Los 10 nombres que más aparecen en la copia local de este aliado.</p>

        @if ($ranking->isEmpty())
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Todavía no hay medicamentos guardados. Use «Actualizar información» para consultar a Tu Dr. Group.</p>
        @else
            <ol class="mt-4 divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($ranking as $row)
                    <li class="flex items-center justify-between gap-4 py-2 text-sm" wire:key="medicine-rank-{{ $loop->index }}">
                        <span class="font-medium text-gray-950 dark:text-white">{{ $loop->iteration }}. {{ $row->medicine_name }}</span>
                        <span class="shrink-0 rounded-md bg-gray-100 px-2 py-1 text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ (int) $row->total }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-filament-widgets::widget>
