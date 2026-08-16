@php
    /** @var \App\Models\Employee $employee */
@endphp

<div class="space-y-4 text-sm">
    <p>
        <strong>{{ $employee->fullName() }}</strong> puede entrar siempre al portal con su cédula o su teléfono.
        Si quiere, ahí mismo configura una clave.
    </p>

    <div class="flex justify-center rounded-2xl bg-white p-4 dark:bg-white/10">
        <img
            src="{{ $qr }}"
            alt="Código QR del portal del empleado"
            width="200"
            height="200"
            class="size-[200px] rounded-xl"
        >
    </div>

    <p class="text-center text-xs text-gray-500">{{ $expiresLabel }}</p>

    <div
        x-data="{ copied: false }"
        class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5"
    >
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Entrada permanente</p>
        <p class="break-all font-mono text-xs">{{ $loginUrl }}</p>
        <button
            type="button"
            class="mt-2 text-xs font-semibold text-cyan-700 dark:text-cyan-300"
            @click="navigator.clipboard.writeText(@js($loginUrl)).then(() => { copied = true; setTimeout(() => copied = false, 1800) })"
        >
            <span x-show="!copied">Copiar enlace</span>
            <span x-show="copied" x-cloak>Copiado</span>
        </button>
    </div>
</div>
