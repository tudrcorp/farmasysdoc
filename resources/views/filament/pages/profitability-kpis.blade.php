<div class="profit-kpis">
    <header class="profit-kpis__hero">
        <div>
            <p class="profit-kpis__eyebrow">Vista CEO · {{ $board['period_label'] }}</p>
            <h1>KPIs de rentabilidad · Pharmadoc</h1>
            <p>Mostrador y convenios por separado. Dólares y bolívares no se convierten entre sí.</p>
        </div>
        <form class="profit-kpis__filters">
            <label>
                Sucursal
                <select wire:model.live="branchId">
                    <option value="">Todas</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Canal
                <select wire:model.live="channel">
                    <option value="all">Mostrador y convenios</option>
                    <option value="counter">Mostrador</option>
                    <option value="agreement">Convenios</option>
                </select>
            </label>
            <label>
                Período
                <select wire:model.live="period">
                    @foreach ($periods as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </header>

    <div class="profit-kpis__grid">
        @foreach ($board['cards'] as $card)
            <article class="profit-kpis__card">
                <div class="profit-kpis__head">
                    <span class="profit-kpis__index">{{ $card['number'] }}</span>
                    <h2>{{ $card['title'] }}</h2>
                </div>
                <p class="profit-kpis__summary">{{ $card['summary'] }}</p>
                <dl class="profit-kpis__metrics">
                    @foreach ($card['metrics'] as $metric)
                        <div class="profit-kpis__metric @if (($metric['tone'] ?? '') === 'text') profit-kpis__metric--text @endif">
                            <dt>{{ $metric['label'] }}</dt>
                            <dd class="@if (($metric['tone'] ?? '') === 'down') is-down @elseif (($metric['tone'] ?? '') === 'up') is-up @endif" title="{{ $metric['value'] }}">{{ $metric['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="profit-kpis__note">{{ $card['note'] }}</p>
            </article>
        @endforeach
    </div>
</div>

<style>
    .profit-kpis { display: flex; flex-direction: column; gap: 1rem; width: 100%; }
    .profit-kpis__hero {
        display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem;
        border-radius: 1.15rem; padding: 1.25rem 1.4rem;
        background: linear-gradient(120deg, #0f766e 0%, #0e7490 48%, #155e75 100%);
        color: #f8fafc; box-shadow: 0 16px 36px rgba(15, 118, 110, 0.18);
    }
    .profit-kpis__eyebrow { margin: 0 0 0.3rem; font-size: 0.72rem; letter-spacing: 0.16em; text-transform: uppercase; color: #99f6e4; }
    .profit-kpis__hero h1 { margin: 0; font-size: clamp(1.4rem, 2vw, 1.85rem); line-height: 1.15; font-weight: 700; }
    .profit-kpis__hero p { margin: 0.4rem 0 0; max-width: 36rem; color: rgba(248, 250, 252, 0.9); font-size: 0.95rem; }
    .profit-kpis__filters { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: end; }
    .profit-kpis__filters label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.68rem; letter-spacing: 0.06em; text-transform: uppercase; color: #ccfbf1; }
    .profit-kpis__filters select {
        min-width: 11rem; border: 0; border-radius: 0.7rem; padding: 0.5rem 0.7rem;
        background: #fff; color: #0f172a; font-size: 0.92rem; text-transform: none; letter-spacing: 0;
    }
    .profit-kpis__grid { display: grid; grid-template-columns: 1fr; gap: 0.85rem; }
    @media (min-width: 900px) { .profit-kpis__grid { grid-template-columns: 1fr 1fr; } }
    @media (min-width: 1280px) { .profit-kpis__grid { grid-template-columns: 1fr 1fr 1fr; } }
    .profit-kpis__card {
        display: flex; flex-direction: column; min-height: 100%; padding: 1rem 1.05rem 0.95rem;
        border-radius: 1rem; background: #fff; border: 1px solid rgba(15, 118, 110, 0.14);
        border-left: 4px solid #facc15; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.045);
    }
    .dark .profit-kpis__card { background: rgba(24, 24, 27, 0.94); border-color: rgba(255, 255, 255, 0.08); border-left-color: #facc15; }
    .profit-kpis__head { display: flex; align-items: center; gap: 0.65rem; }
    .profit-kpis__index {
        width: 1.85rem; height: 1.85rem; border-radius: 999px; background: #facc15; color: #14532d;
        font-weight: 700; display: flex; align-items: center; justify-content: center;
    }
    .profit-kpis__head h2 { margin: 0; font-size: 1.02rem; line-height: 1.25; color: #0f766e; }
    .dark .profit-kpis__head h2 { color: #5eead4; }
    .profit-kpis__summary { margin: 0.45rem 0 0.85rem; color: #52606d; font-size: 0.88rem; line-height: 1.4; min-height: 2.5rem; }
    .dark .profit-kpis__summary, .dark .profit-kpis__note { color: #a1a1aa; }
    .profit-kpis__metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(7.5rem, 1fr)); gap: 0.7rem 0.85rem; margin: 0; }
    .profit-kpis__metric--text { grid-column: 1 / -1; }
    .profit-kpis__metric dt { font-size: 0.68rem; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
    .profit-kpis__metric dd {
        margin: 0.15rem 0 0; font-size: 1.12rem; font-weight: 700; font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em; color: #0f172a; line-height: 1.25;
    }
    .profit-kpis__metric--text dd {
        font-size: 0.95rem; font-weight: 650; display: -webkit-box; -webkit-line-clamp: 2;
        -webkit-box-orient: vertical; overflow: hidden;
    }
    .profit-kpis__metric dd.is-down { color: #be123c; }
    .profit-kpis__metric dd.is-up { color: #047857; }
    .dark .profit-kpis__metric dd { color: #fafafa; }
    .dark .profit-kpis__metric dd.is-down { color: #fb7185; }
    .dark .profit-kpis__metric dd.is-up { color: #34d399; }
    .profit-kpis__note { margin: auto 0 0; padding-top: 0.8rem; font-size: 0.8rem; line-height: 1.35; color: #64748b; }
</style>
