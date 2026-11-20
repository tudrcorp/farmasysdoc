@php
    $polling = $this->isSyncRunning() ? '5s' : null;
@endphp

<x-filament-panels::page>
    <div @if ($polling) wire:poll.{{ $polling }} @endif>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
