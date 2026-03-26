@php
    /** @var array<string, mixed> $m */
@endphp
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
    <div class="rounded-2xl border border-zinc-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm transition duration-200 ease-out hover:shadow-md dark:border-white/10 dark:bg-zinc-900/60">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Compras completadas</p>
        <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $m['purchases_count'] }}</p>
    </div>
    <div class="rounded-2xl border border-zinc-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm transition duration-200 ease-out hover:shadow-md dark:border-white/10 dark:bg-zinc-900/60 sm:col-span-2 xl:col-span-2">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Producto más comprado</p>
        <p class="mt-1 text-lg font-semibold leading-snug text-zinc-900 dark:text-white">{{ $m['favorite_product'] }}</p>
    </div>
    <div class="rounded-2xl border border-zinc-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm transition duration-200 ease-out hover:shadow-md dark:border-white/10 dark:bg-zinc-900/60">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Compra más alta</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $m['max_purchase'] }}</p>
    </div>
    <div class="rounded-2xl border border-zinc-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm transition duration-200 ease-out hover:shadow-md dark:border-white/10 dark:bg-zinc-900/60">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total gastado</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $m['total_spent'] }}</p>
    </div>
    <div class="rounded-2xl border border-zinc-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm transition duration-200 ease-out hover:shadow-md dark:border-white/10 dark:bg-zinc-900/60">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Ticket promedio</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $m['avg_ticket'] }}</p>
    </div>
    <div class="rounded-2xl border border-zinc-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm transition duration-200 ease-out hover:shadow-md dark:border-white/10 dark:bg-zinc-900/60">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Sucursales distintas</p>
        <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $m['branches_visited'] }}</p>
    </div>
    <div class="rounded-2xl border border-zinc-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm transition duration-200 ease-out hover:shadow-md dark:border-white/10 dark:bg-zinc-900/60">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Última compra</p>
        <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $m['last_purchase_at'] ?? '—' }}</p>
    </div>
    <div class="rounded-2xl border border-zinc-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm transition duration-200 ease-out hover:shadow-md dark:border-white/10 dark:bg-zinc-900/60">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Primera compra</p>
        <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $m['first_purchase_at'] ?? '—' }}</p>
    </div>
</div>
