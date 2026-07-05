@props([
    'lastResult',
    'bdvOutcomeOk',
])

<div
    @class([
        'farmadoc-bdv-pm-result',
        'farmadoc-bdv-pm-result--ok' => $bdvOutcomeOk,
        'farmadoc-bdv-pm-result--fail' => ! $bdvOutcomeOk,
    ])
>
    <p class="farmadoc-bdv-pm-result__title">
        {{ $bdvOutcomeOk ? 'Conciliación exitosa' : 'Conciliación no confirmada' }}
    </p>
    <p class="farmadoc-bdv-pm-result__subtitle">{{ $lastResult['operation'] ?? 'Conciliación Pagomóvil' }}</p>

    @if (! empty($lastResult['highlight_codes']))
        <dl class="farmadoc-bdv-pm-result__codes">
            @foreach ($lastResult['highlight_codes'] as $code)
                <div>
                    <dt>{{ strtoupper($code['key']) }}</dt>
                    <dd>{{ $code['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif

    <pre class="farmadoc-bdv-pm-result__json">{{ is_string($lastResult['body']) ? $lastResult['body'] : json_encode($lastResult['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</div>
