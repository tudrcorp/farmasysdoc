{{-- Fondo y selector de tema (alineado con welcome) en páginas simples de auth --}}
<div class="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_120%_80%_at_50%_-20%,rgba(24,172,178,0.28),transparent_55%)] dark:bg-[radial-gradient(ellipse_120%_80%_at_50%_-20%,rgba(24,172,178,0.18),transparent_55%)]"></div>
    <div class="absolute -left-24 top-10 h-[28rem] w-[28rem] rounded-full bg-cyan-400/35 blur-3xl dark:bg-cyan-500/20"></div>
    <div class="absolute -right-20 top-32 h-[26rem] w-[26rem] rounded-full bg-[#FCE422]/30 blur-3xl dark:bg-[#FCE422]/12"></div>
    <div class="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-teal-500/25 blur-3xl dark:bg-teal-600/15"></div>
    <div class="absolute inset-0 bg-[linear-gradient(to_bottom,transparent,rgba(9,9,11,0.04))] dark:bg-[linear-gradient(to_bottom,transparent,rgba(0,0,0,0.35))]"></div>
</div>

<div
    class="fixed right-3 top-3 z-[100] sm:right-6 sm:top-6"
    role="group"
    aria-label="Seleccionar tema de la interfaz"
>
    <div class="flex items-center gap-0.5 rounded-[1.35rem] border border-zinc-200/90 bg-white/85 p-1 shadow-lg shadow-zinc-900/10 backdrop-blur-xl dark:border-white/15 dark:bg-zinc-900/80 dark:shadow-black/40">
        <button
            type="button"
            id="farmaadmin-theme-light"
            class="farmaadmin-theme-seg inline-flex min-w-22 items-center justify-center gap-1.5 rounded-[1.1rem] px-3 py-2 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-zinc-950"
            aria-pressed="false"
        >
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
            </svg>
            Claro
        </button>
        <button
            type="button"
            id="farmaadmin-theme-dark"
            class="farmaadmin-theme-seg inline-flex min-w-22 items-center justify-center gap-1.5 rounded-[1.1rem] px-3 py-2 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-zinc-950"
            aria-pressed="false"
        >
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M21 14.5A8.5 8.5 0 0 1 9.5 3a8.5 8.5 0 1 0 11.5 11.5Z"></path>
            </svg>
            Oscuro
        </button>
    </div>
</div>

<script>
    (function () {
        const btnLight = document.getElementById('farmaadmin-theme-light');
        const btnDark = document.getElementById('farmaadmin-theme-dark');
        if (!btnLight || !btnDark) {
            return;
        }

        const segBase =
            'farmaadmin-theme-seg inline-flex min-w-22 items-center justify-center gap-1.5 rounded-[1.1rem] px-3 py-2 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-zinc-950';

        const segInactive =
            'text-zinc-500 hover:bg-zinc-100/80 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-zinc-100';

        const segActive =
            'bg-gradient-to-b from-cyan-500/20 to-teal-600/15 text-cyan-950 shadow-inner shadow-cyan-500/20 dark:from-cyan-400/25 dark:to-teal-900/40 dark:text-white dark:shadow-cyan-900/30';

        function syncSegmentUi(isDark) {
            btnLight.className = `${segBase} ${isDark ? segInactive : segActive}`;
            btnDark.className = `${segBase} ${isDark ? segActive : segInactive}`;
            btnLight.setAttribute('aria-pressed', (!isDark).toString());
            btnDark.setAttribute('aria-pressed', isDark.toString());
        }

        function applyTheme(mode) {
            localStorage.setItem('theme', mode);
            window.theme = mode;
            const isDark =
                mode === 'dark' ||
                (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
            syncSegmentUi(isDark);
        }

        function syncFromStorage() {
            const mode = localStorage.getItem('theme') ?? @json(filament()->getDefaultThemeMode()->value);
            window.theme = mode;
            const isDark =
                mode === 'dark' ||
                (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
            syncSegmentUi(isDark);
        }

        btnLight.addEventListener('click', () => applyTheme('light'));
        btnDark.addEventListener('click', () => applyTheme('dark'));

        document.addEventListener('DOMContentLoaded', syncFromStorage);
        document.addEventListener('livewire:navigated', syncFromStorage);
    })();
</script>
