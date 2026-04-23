@php
    /** @var array{match: bool, declared: float, calculated: float, currency_prefix: string, diff: float} $footer */
    $pfx = $footer['currency_prefix'] ?? '$';
    $fmt = static function (float $v) use ($pfx): string {
        return $pfx . number_format($v, 2, ',', '.');
    };
@endphp

<div
    @class([
        'rounded-lg border px-4 py-3 text-sm',
        'border-emerald-500/60 bg-emerald-50 text-emerald-950 dark:border-emerald-500/40 dark:bg-emerald-950/35 dark:text-emerald-50' => $footer['match'],
        'border-red-500/60 bg-red-50 text-red-950 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-50' => ! $footer['match'],
    ])
>
    <p class="font-semibold">
        @if ($footer['match'])
            Total de factura: coincide con el cálculo del sistema
        @else
            Total de factura: no coincide con el cálculo del sistema
        @endif
    </p>
    <dl class="mt-2 grid gap-1 sm:grid-cols-2">
        <div class="flex justify-between gap-2 sm:block">
            <dt class="text-xs opacity-80">Declarado (proveedor)</dt>
            <dd class="font-mono font-medium tabular-nums">{{ $fmt($footer['declared']) }}</dd>
        </div>
        <div class="flex justify-between gap-2 sm:block">
            <dt class="text-xs opacity-80">Calculado (líneas)</dt>
            <dd class="font-mono font-medium tabular-nums">{{ $fmt($footer['calculated']) }}</dd>
        </div>
    </dl>
    @if (! $footer['match'])
        <p class="mt-2 text-xs opacity-90">
            El total declarado debe coincidir con el calculado salvo la última cifra decimal (misma parte entera y misma décima; hasta ±9 centésimas). Diferencia actual: {{ $fmt($footer['diff']) }}.
            Cierre el resumen, corrija líneas o el total declarado y vuelva a intentar.
        </p>
    @endif
</div>
