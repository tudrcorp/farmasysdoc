@php
    /**
     * Hoja para ordenar resultados (sustituye al <select> nativo).
     *
     * @var array<string, string> $sortOptions
     * @var string                $sort
     * @var string                $setSortMethod
     */
    $setSortMethod = $setSortMethod ?? 'setSort';
@endphp

<div class="sh-sort" x-data="{ open: false }">
    <button type="button" class="sh-chip" @click="open = true" aria-haspopup="dialog">
        @include('shop.partials.icon', ['icon' => 'sliders'])
        {{ $sortOptions[$sort] ?? 'Ordenar' }}
    </button>

    <div class="sh-backdrop" x-show="open" x-cloak x-transition.opacity.duration.200ms @click="open = false" aria-hidden="true"></div>

    <div
        class="sh-sheet"
        x-show="open"
        x-cloak
        x-transition:enter="sh-sheet-anim"
        x-transition:enter-start="sh-sheet-anim-start"
        x-transition:enter-end="sh-sheet-anim-end"
        x-transition:leave="sh-sheet-anim sh-sheet-anim-leave"
        x-transition:leave-start="sh-sheet-anim-end"
        x-transition:leave-end="sh-sheet-anim-start"
        role="dialog"
        aria-label="Ordenar"
        @keydown.escape.window="open = false"
    >
        <span class="sh-sheet__handle" style="margin:0.7rem auto 0.55rem;"></span>
        <p class="sh-sheet__kicker">Ordenar por</p>

        <div class="sh-sheet__group">
            @foreach ($sortOptions as $value => $label)
                <button
                    type="button"
                    @class(['sh-sheet__item', 'is-active' => $sort === $value])
                    wire:click="{{ $setSortMethod }}('{{ $value }}')"
                    @click="open = false"
                >
                    <span class="sh-sheet__copy">
                        <strong>{{ $label }}</strong>
                    </span>
                    @if ($sort === $value)
                        <span class="sh-sheet__check" aria-hidden="true">
                            @include('shop.partials.icon', ['icon' => 'check'])
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="sh-sheet__actions">
            <button type="button" class="sh-btn sh-btn--quiet sh-btn--block" @click="open = false">Cerrar</button>
        </div>
    </div>
</div>
