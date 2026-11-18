@php
    /** @var \App\Support\Partners\AgreementTermStatus $term */
    $tones = [
        'ok' => ['bar' => 'bg-success-500', 'text' => 'text-success-700 dark:text-success-400', 'chip' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/30'],
        'warning' => ['bar' => 'bg-warning-500', 'text' => 'text-warning-700 dark:text-warning-400', 'chip' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-400/30'],
        'danger' => ['bar' => 'bg-danger-500', 'text' => 'text-danger-700 dark:text-danger-400', 'chip' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/30'],
        'pending' => ['bar' => 'bg-info-500', 'text' => 'text-info-700 dark:text-info-400', 'chip' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400 dark:ring-info-400/30'],
        'unknown' => ['bar' => 'bg-gray-400', 'text' => 'text-gray-600 dark:text-gray-300', 'chip' => 'bg-gray-50 text-gray-600 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/15'],
    ];
    $tone = $tones[$term->tone] ?? $tones['unknown'];
    $progress = $term->progressPercent();
    $fmt = fn (?int $days): string => $days === null ? '—' : number_format($days, 0, ',', '.');
@endphp

<div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
    <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-gray-100 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
        <div class="bg-white px-4 py-3 dark:bg-gray-900/60">
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Creación</dt>
            <dd class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $term->startsOn?->format('d/m/Y') ?? '—' }}</dd>
        </div>
        <div class="bg-white px-4 py-3 dark:bg-gray-900/60">
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Vencimiento</dt>
            <dd class="mt-1 text-base font-semibold {{ $term->endsOn ? 'text-gray-950 dark:text-white' : 'text-gray-400' }}">{{ $term->endsOn?->format('d/m/Y') ?? 'Sin definir' }}</dd>
        </div>
        <div class="bg-white px-4 py-3 dark:bg-gray-900/60">
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Días de vigencia</dt>
            <dd class="mt-1 text-base font-semibold tabular-nums text-gray-950 dark:text-white">{{ $fmt($term->totalDays) }}</dd>
        </div>
        <div class="bg-white px-4 py-3 dark:bg-gray-900/60">
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Días restantes</dt>
            <dd class="mt-1 text-base font-bold tabular-nums {{ $tone['text'] }}">{{ $term->remainingDays === null ? '—' : $fmt(max(0, $term->remainingDays)) }}</dd>
        </div>
    </dl>

    <div class="flex flex-col justify-center gap-3 rounded-xl bg-white px-5 py-4 ring-1 ring-gray-950/5 dark:bg-gray-900/60 dark:ring-white/10">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset {{ $tone['chip'] }}">{{ $term->label }}</span>
            @if ($progress !== null)
                <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $progress }}% de la vigencia transcurrido</span>
            @endif
        </div>
        @if ($progress !== null)
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                <div class="h-full rounded-full {{ $tone['bar'] }}" style="width: {{ $progress }}%"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ $term->startsOn?->format('d/m/Y') }}</span>
                <span>{{ $term->endsOn?->format('d/m/Y') }}</span>
            </div>
        @endif
        @if ($forPartner ?? false)
            @if ($term->tone === 'warning')
                <p class="text-xs text-warning-700 dark:text-warning-400">Su convenio está por vencer: su ejecutivo de Farmadoc lo contactará para la renovación.</p>
            @elseif ($term->tone === 'danger' && $term->remainingDays !== null)
                <p class="text-xs text-danger-700 dark:text-danger-400">El convenio venció o vence hoy. Comuníquese con su ejecutivo de Farmadoc.</p>
            @endif
        @elseif ($term->tone === 'warning')
            <p class="text-xs text-warning-700 dark:text-warning-400">Está dentro del período de aviso: conviene iniciar la renovación.</p>
        @elseif ($term->tone === 'danger' && $term->remainingDays !== null)
            <p class="text-xs text-danger-700 dark:text-danger-400">El convenio venció o vence hoy: registre la renovación.</p>
        @elseif ($term->tone === 'unknown')
            <p class="text-xs text-gray-500 dark:text-gray-400">Indique la fecha de vencimiento en «Editar» para controlar la vigencia.</p>
        @endif
    </div>
</div>
