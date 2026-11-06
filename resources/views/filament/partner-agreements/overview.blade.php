<x-filament-widgets::widget>
    {{-- Estilos locales: no dependen de recompilar el tema (usan las variables de color de Filament). --}}
    <style>
        .fpa-overview { display: grid; gap: 1rem; grid-template-columns: repeat(4, minmax(0, 1fr)); }
        @media (max-width: 1279px) { .fpa-overview { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 639px) { .fpa-overview { grid-template-columns: minmax(0, 1fr); } }
    
        .fpa-card {
            --fpa-tone: var(--gray-400);
            position: relative; display: flex; flex-direction: column; overflow: hidden;
            padding: 1.125rem 1.25rem 1rem 1.5rem; border-radius: 0.875rem;
            background: #fff; border: 1px solid rgb(3 7 18 / 0.06);
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
            color: inherit; text-decoration: none;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .fpa-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px -8px rgb(0 0 0 / 0.18); border-color: color-mix(in oklab, var(--fpa-tone) 35%, transparent); }
        .fpa-card:focus-visible { outline: 2px solid var(--primary-500); outline-offset: 2px; }
        .dark .fpa-card { background: var(--gray-900); border-color: rgb(255 255 255 / 0.08); box-shadow: none; }
    
        .fpa-card[data-tone="success"] { --fpa-tone: var(--success-500); }
        .fpa-card[data-tone="warning"] { --fpa-tone: var(--warning-500); }
        .fpa-card[data-tone="info"] { --fpa-tone: var(--info-500); }
        .fpa-card[data-tone="danger"] { --fpa-tone: var(--danger-500); }
        .fpa-card[data-tone="muted"] { --fpa-tone: var(--gray-400); }
    
        .fpa-accent { position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--fpa-tone); }
        .fpa-card[data-tone="muted"] .fpa-accent { opacity: .35; }
    
        .fpa-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
        .fpa-label { font-size: .8125rem; font-weight: 500; color: var(--gray-500); }
        .dark .fpa-label { color: var(--gray-400); }
        .fpa-value { margin-top: .125rem; font-size: 2rem; line-height: 1.1; font-weight: 600; letter-spacing: -.02em; font-variant-numeric: tabular-nums; color: var(--gray-950); }
        .dark .fpa-value { color: #fff; }
        .fpa-card:not([data-tone="success"]):not([data-tone="muted"]) .fpa-value { color: var(--fpa-tone); }
        .fpa-card[data-tone="muted"] .fpa-value { color: var(--gray-400); }
        .dark .fpa-card[data-tone="muted"] .fpa-value { color: var(--gray-500); }
    
        .fpa-icon {
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            width: 2.5rem; height: 2.5rem; border-radius: .625rem;
            color: var(--fpa-tone);
            background: color-mix(in oklab, var(--fpa-tone) 12%, transparent);
            box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--fpa-tone) 22%, transparent);
        }
        .fpa-icon svg { width: 1.25rem; height: 1.25rem; }
    
        .fpa-bar { display: flex; gap: 2px; height: 6px; margin-top: .875rem; overflow: hidden; border-radius: 999px; background: var(--gray-100); }
        .dark .fpa-bar { background: rgb(255 255 255 / 0.06); }
        .fpa-seg { height: 100%; }
        .fpa-seg[data-tone="success"], .fpa-dot[data-tone="success"] { background: var(--success-500); }
        .fpa-seg[data-tone="warning"], .fpa-dot[data-tone="warning"] { background: var(--warning-500); }
        .fpa-seg[data-tone="info"], .fpa-dot[data-tone="info"] { background: var(--info-500); }
        .fpa-seg[data-tone="danger"], .fpa-dot[data-tone="danger"] { background: var(--danger-500); }
        .fpa-seg[data-tone="track"] { background: transparent; }
    
        .fpa-legend { display: flex; flex-wrap: wrap; gap: .25rem .75rem; margin-top: .5rem; font-size: .75rem; color: var(--gray-500); }
        .fpa-legend b { font-weight: 600; color: var(--gray-700); font-variant-numeric: tabular-nums; }
        .dark .fpa-legend b { color: var(--gray-200); }
        .fpa-legend span { display: inline-flex; align-items: center; gap: .375rem; }
        .fpa-dot { width: .5rem; height: .5rem; border-radius: 999px; }
    
        .fpa-foot { display: flex; align-items: flex-end; justify-content: space-between; gap: .5rem; margin-top: auto; padding-top: .875rem; }
        .fpa-foot-inner { min-width: 0; padding-top: .75rem; border-top: 1px solid var(--gray-100); flex: 1; display: flex; align-items: flex-end; justify-content: space-between; gap: .5rem; }
        .dark .fpa-foot-inner { border-top-color: rgb(255 255 255 / 0.06); }
        .fpa-detail { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .8125rem; color: var(--gray-700); }
        .dark .fpa-detail { color: var(--gray-200); }
        .fpa-card[data-tone="muted"] .fpa-detail { color: var(--gray-400); }
        .dark .fpa-card[data-tone="muted"] .fpa-detail { color: var(--gray-500); }
        .fpa-chip {
            display: inline-flex; margin-top: .375rem; padding: .125rem .5rem; border-radius: .375rem;
            font-size: .75rem; font-weight: 500; color: var(--fpa-tone);
            background: color-mix(in oklab, var(--fpa-tone) 12%, transparent);
        }
        .fpa-arrow { width: 1rem; height: 1rem; flex-shrink: 0; color: var(--gray-300); transition: transform .15s ease, color .15s ease; }
        .dark .fpa-arrow { color: var(--gray-600); }
        .fpa-card:hover .fpa-arrow { transform: translateX(2px); color: var(--fpa-tone); }
    </style>

    <div class="fpa-overview">
        @foreach ($cards as $card)
            <a href="{{ $card['url'] }}" wire:navigate class="fpa-card" data-tone="{{ $card['tone'] }}">
                <span class="fpa-accent" aria-hidden="true"></span>

                <div class="fpa-head">
                    <div style="min-width: 0">
                        <div class="fpa-label">{{ $card['label'] }}</div>
                        <div class="fpa-value">{{ number_format($card['count'], 0, ',', '.') }}</div>
                    </div>
                    <span class="fpa-icon">
                        <x-filament::icon :icon="$card['icon']" />
                    </span>
                </div>

                @if (count($card['progress']) > 0)
                    <div class="fpa-bar" aria-hidden="true">
                        @foreach ($card['progress'] as $segment)
                            <span class="fpa-seg" data-tone="{{ $segment['tone'] }}" style="width: {{ $segment['width'] }}%"></span>
                        @endforeach
                    </div>
                @endif

                @if (count($card['legend']) > 0)
                    <div class="fpa-legend">
                        @foreach ($card['legend'] as $item)
                            <span><i class="fpa-dot" data-tone="{{ $item['tone'] }}"></i>{{ $item['label'] }}: <b>{{ $item['value'] }}</b></span>
                        @endforeach
                    </div>
                @endif

                <div class="fpa-foot">
                    <div class="fpa-foot-inner">
                        <div style="min-width: 0">
                            <div class="fpa-detail" title="{{ $card['detail'] }}">{{ $card['detail'] }}</div>
                            @if (filled($card['chip']))
                                <span class="fpa-chip">{{ $card['chip'] }}</span>
                            @endif
                        </div>
                        <x-filament::icon icon="heroicon-m-arrow-right" class="fpa-arrow" />
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
