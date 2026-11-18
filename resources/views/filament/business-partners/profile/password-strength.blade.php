{{-- Medidor de seguridad en vivo (no envía nada: lee el valor del campo en el navegador). --}}
<div
    x-data="{
        get pw() { return $wire.get('data.password') || '' },
        get checks() {
            const pw = this.pw
            return [
                { label: '12 caracteres o más', ok: pw.length >= 12 },
                { label: 'Mayúscula y minúscula', ok: /[a-z]/.test(pw) && /[A-Z]/.test(pw) },
                { label: 'Un número', ok: /\d/.test(pw) },
                { label: 'Un símbolo', ok: /[^A-Za-z0-9]/.test(pw) },
            ]
        },
        get score() { return this.checks.filter(c => c.ok).length },
        get label() { return ['Muy débil', 'Débil', 'Aceptable', 'Buena', 'Excelente'][this.score] },
    }"
    x-show="pw.length > 0"
    x-cloak
    x-transition.opacity
    class="mt-2 space-y-2"
>
    <div class="flex items-center gap-3">
        <div class="grid flex-1 grid-cols-4 gap-1.5" aria-hidden="true">
            <template x-for="i in 4" :key="i">
                <span
                    class="h-1.5 rounded-full transition-colors duration-300"
                    :class="i <= score
                        ? (score <= 1 ? 'bg-danger-500' : (score === 2 ? 'bg-warning-500' : (score === 3 ? 'bg-primary-400' : 'bg-success-500')))
                        : 'bg-gray-200 dark:bg-white/10'"
                ></span>
            </template>
        </div>
        <span class="w-20 text-right text-xs font-semibold"
            :class="score <= 1 ? 'text-danger-600 dark:text-danger-400' : (score === 2 ? 'text-warning-600 dark:text-warning-400' : 'text-success-600 dark:text-success-400')"
            x-text="label"></span>
    </div>
    <ul class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
        <template x-for="check in checks" :key="check.label">
            <li class="inline-flex items-center gap-1.5" :class="check.ok ? 'text-success-700 dark:text-success-400' : 'text-gray-500 dark:text-gray-400'">
                <span class="flex h-3.5 w-3.5 items-center justify-center rounded-full text-[9px] font-bold" :class="check.ok ? 'bg-success-500 text-white' : 'bg-gray-200 dark:bg-white/10'" x-text="check.ok ? '✓' : ''"></span>
                <span x-text="check.label"></span>
            </li>
        </template>
    </ul>
</div>
