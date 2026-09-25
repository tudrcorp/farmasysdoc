<div class="exec-kpis">
    <header class="exec-kpis__hero">
        <div>
            <p class="exec-kpis__eyebrow">Vista CEO · {{ $board['period_label'] }}</p>
            <h1>KPIs directivos · Pharmadoc</h1>
            <p>Nueve lecturas para decidir por sucursal y por empresa. Dólares y bolívares no se convierten entre sí.</p>
        </div>
    </header>

    <div class="exec-kpis__grid">
        @foreach ($board['cards'] as $card)
            <article class="exec-kpis__card">
                <div class="exec-kpis__head">
                    <span class="exec-kpis__index">{{ $card['number'] }}</span>
                    <h2>{{ $card['title'] }}</h2>
                </div>
                <p class="exec-kpis__summary">{{ $card['summary'] }}</p>
                <dl class="exec-kpis__metrics">
                    @foreach ($card['metrics'] as $metric)
                        <div class="exec-kpis__metric @if (($metric['tone'] ?? '') === 'text') exec-kpis__metric--text @endif">
                            <dt>{{ $metric['label'] }}</dt>
                            <dd class="@if (($metric['tone'] ?? '') === 'down') is-down @elseif (($metric['tone'] ?? '') === 'up') is-up @endif" title="{{ $metric['value'] }}">{{ $metric['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="exec-kpis__note">{{ $card['note'] }}</p>
            </article>
        @endforeach
    </div>

    <aside class="exec-kpis__ceo">
        <strong>Vista CEO</strong>
        <p>{{ $board['ceo_note'] }}</p>
    </aside>
</div>

<style>
    .exec-kpis {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        width: 100%;
    }

    .exec-kpis__hero {
        border-radius: 1.15rem;
        padding: 1.35rem 1.6rem;
        background: linear-gradient(120deg, #0f766e 0%, #0e7490 48%, #155e75 100%);
        color: #f8fafc;
        box-shadow: 0 16px 36px rgba(15, 118, 110, 0.18);
    }

    .exec-kpis__eyebrow {
        margin: 0 0 0.3rem;
        font-size: 0.72rem;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #99f6e4;
    }

    .exec-kpis__hero h1 {
        margin: 0;
        font-size: clamp(1.45rem, 2vw, 1.9rem);
        line-height: 1.15;
        font-weight: 700;
    }

    .exec-kpis__hero p:last-child {
        margin: 0.4rem 0 0;
        max-width: 44rem;
        color: rgba(248, 250, 252, 0.9);
        font-size: 0.95rem;
    }

    .exec-kpis__grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.85rem;
        align-items: stretch;
    }

    @media (min-width: 900px) {
        .exec-kpis__grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (min-width: 1280px) {
        .exec-kpis__grid {
            grid-template-columns: 1fr 1fr 1fr;
        }
    }

    .exec-kpis__card {
        display: flex;
        flex-direction: column;
        min-height: 100%;
        padding: 1rem 1.05rem 0.95rem;
        border-radius: 1rem;
        background: #fff;
        border: 1px solid rgba(15, 118, 110, 0.14);
        border-left: 4px solid #facc15;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.045);
    }

    .dark .exec-kpis__card {
        background: rgba(24, 24, 27, 0.94);
        border-color: rgba(255, 255, 255, 0.08);
        border-left-color: #facc15;
    }

    .exec-kpis__head {
        display: flex;
        align-items: center;
        gap: 0.65rem;
    }

    .exec-kpis__index {
        flex: 0 0 auto;
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 999px;
        background: #facc15;
        color: #14532d;
        font-weight: 700;
        font-size: 0.92rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .exec-kpis__head h2 {
        margin: 0;
        font-size: 1.02rem;
        line-height: 1.25;
        color: #0f766e;
    }

    .dark .exec-kpis__head h2 {
        color: #5eead4;
    }

    .exec-kpis__summary {
        margin: 0.45rem 0 0.85rem;
        color: #52606d;
        font-size: 0.88rem;
        line-height: 1.4;
        min-height: 2.5rem;
    }

    .dark .exec-kpis__summary,
    .dark .exec-kpis__note {
        color: #a1a1aa;
    }

    .exec-kpis__metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(7.5rem, 1fr));
        gap: 0.7rem 0.85rem;
        margin: 0;
    }

    .exec-kpis__metric--text {
        grid-column: 1 / -1;
    }

    .exec-kpis__metric dt {
        font-size: 0.68rem;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #64748b;
    }

    .exec-kpis__metric dd {
        margin: 0.15rem 0 0;
        font-size: 1.12rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
        color: #0f172a;
        line-height: 1.25;
    }

    .exec-kpis__metric--text dd {
        font-size: 0.95rem;
        font-weight: 650;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .exec-kpis__metric dd.is-down {
        color: #be123c;
    }

    .exec-kpis__metric dd.is-up {
        color: #047857;
    }

    .dark .exec-kpis__metric dd {
        color: #fafafa;
    }

    .dark .exec-kpis__metric dd.is-down {
        color: #fb7185;
    }

    .dark .exec-kpis__metric dd.is-up {
        color: #34d399;
    }

    .exec-kpis__note {
        margin: auto 0 0;
        padding-top: 0.8rem;
        font-size: 0.8rem;
        line-height: 1.35;
        color: #64748b;
    }

    .exec-kpis__ceo {
        display: flex;
        gap: 0.85rem;
        align-items: baseline;
        border-radius: 1rem;
        padding: 0.9rem 1.15rem;
        background: #ecfeff;
        border: 1px solid #a5f3fc;
    }

    .dark .exec-kpis__ceo {
        background: rgba(8, 47, 73, 0.55);
        border-color: rgba(103, 232, 249, 0.25);
        color: #ecfeff;
    }

    .exec-kpis__ceo strong {
        flex: 0 0 auto;
        color: #0f766e;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        font-size: 0.78rem;
    }

    .dark .exec-kpis__ceo strong {
        color: #5eead4;
    }

    .exec-kpis__ceo p {
        margin: 0;
    }

    @media (max-width: 700px) {
        .exec-kpis__ceo {
            flex-direction: column;
            gap: 0.25rem;
        }
    }
</style>
