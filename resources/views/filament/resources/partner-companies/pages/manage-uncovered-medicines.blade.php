@php
    /** @var \App\Models\PartnerUncoveredMedicineConnection|null $connection */
    $connection = $this->connection();
    $polling = $this->isSyncRunning() ? '5s' : null;
    $profileLines = $connection?->profileLines() ?? [];
@endphp

<x-filament-panels::page>
    <div @if ($polling) wire:poll.{{ $polling }} @endif class="flex flex-col gap-4">
        @if ($profileLines !== [])
            <dl class="grid gap-3 rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-gray-950/5 sm:grid-cols-2 dark:bg-gray-900 dark:ring-white/10">
                @foreach ($profileLines as $line)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ $line['label'] }}</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $line['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        {{ $this->table }}
    </div>
</x-filament-panels::page>
