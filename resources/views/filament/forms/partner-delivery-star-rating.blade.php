@php
    $fieldWrapperView = $getFieldWrapperView();
    $extraAttributeBag = $getExtraAttributeBag();
    $statePath = $getStatePath();
    $raw = $getState();
    $current = is_numeric($raw) ? (int) $raw : 0;
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    class="fi-fo-partner-delivery-star-rating-wrp"
>
    <div
        {{ \Filament\Support\prepare_inherited_attributes($extraAttributeBag)->class(['flex flex-col items-center gap-1']) }}
    >
        <div
            class="fi-partner-delivery-stars flex flex-wrap items-center justify-center gap-1 sm:gap-2"
            role="group"
            aria-label="Calificación de 1 a 5 estrellas"
        >
            @foreach (range(1, 5) as $star)
                <button
                    type="button"
                    wire:click="$set({{ \Illuminate\Support\Js::from($statePath) }}, {{ $star }})"
                    wire:key="partner-star-{{ $star }}-{{ str_replace('.', '-', (string) $statePath) }}"
                    @class([
                        'rounded-lg p-1.5 transition-all duration-150 ease-out',
                        'scale-105 ring-2 ring-amber-400/70 ring-offset-2 ring-offset-white dark:ring-offset-zinc-900' => $star === $current,
                        'hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400/80 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-zinc-900' => true,
                    ])
                    aria-pressed="{{ $star === $current ? 'true' : 'false' }}"
                    aria-label="{{ $star }} {{ $star === 1 ? 'estrella' : 'estrellas' }}"
                >
                    <span
                        @class([
                            'block text-4xl leading-none tracking-tight',
                            'text-amber-400 drop-shadow-[0_0_1px_rgba(251,191,36,0.9)]' => $star <= $current,
                            'text-zinc-300 dark:text-zinc-600' => $star > $current,
                        ])
                    >★</span>
                </button>
            @endforeach
        </div>
        @if ($current >= 1)
            <p class="text-center text-xs font-medium text-zinc-500 dark:text-zinc-400">
                {{ $current }}/5
            </p>
        @endif
    </div>
</x-dynamic-component>
