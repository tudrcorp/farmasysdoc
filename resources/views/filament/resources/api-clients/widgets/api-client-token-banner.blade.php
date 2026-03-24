@php
    $token = $revealedPlainToken ?? null;
@endphp

{{-- Un único elemento raíz obligatorio para widgets Livewire --}}
<div wire:key="api-client-token-banner-root">
    @if (filled($token))
        <div
            wire:key="api-token-banner"
            class="fi-api-token-banner mb-6 overflow-hidden rounded-3xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/95 via-white/90 to-teal-50/80 p-1 shadow-lg shadow-emerald-900/5 ring-1 ring-emerald-500/10 dark:border-emerald-500/25 dark:from-emerald-950/50 dark:via-gray-900/80 dark:to-teal-950/40 dark:shadow-black/40"
            role="status"
            aria-live="polite"
            x-data="{ copied: false }"
        >
            <div class="rounded-[1.35rem] bg-white/70 p-5 backdrop-blur-md dark:bg-gray-900/50">
                <div class="flex items-start gap-3">
                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-700 dark:text-emerald-400/15 dark:text-emerald-300">
                        <x-filament::icon icon="heroicon-o-key" class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold tracking-tight text-gray-900 dark:text-white">
                            Token generado
                        </h3>
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                            Cópialo ahora. Por seguridad no podremos mostrarlo de nuevo.
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-stretch">
                    <div
                        class="min-w-0 flex-1 rounded-2xl border border-gray-200/90 bg-gray-50/80 px-4 py-3 font-mono text-xs leading-relaxed text-gray-900 shadow-inner dark:border-white/10 dark:bg-black/30 dark:text-gray-100 sm:text-sm"
                    >
                        <span class="select-all break-all">{{ $token }}</span>
                    </div>
                    <button
                        type="button"
                        x-on:click="
                            navigator.clipboard.writeText(@js($token));
                            copied = true;
                            setTimeout(() => (copied = false), 2200);
                        "
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-2xl bg-primary-500 px-5 py-3 text-sm font-semibold text-primary-950 shadow-md transition hover:bg-primary-400 active:scale-[0.98] dark:text-primary-950"
                    >
                        <x-filament::icon icon="heroicon-o-clipboard-document" class="size-5" />
                        <span x-text="copied ? 'Copiado' : 'Copiar token'">Copiar token</span>
                    </button>
                </div>

                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    Encabezado:
                    <code class="rounded-md bg-gray-200/80 px-1.5 py-0.5 font-mono text-[0.7rem] dark:bg-white/10">Authorization: Bearer …</code>
                </p>
            </div>
        </div>
    @endif
</div>
