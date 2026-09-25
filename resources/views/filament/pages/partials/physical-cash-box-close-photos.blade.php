<div class="grid gap-6 md:grid-cols-2">
    <figure class="space-y-2">
        <figcaption class="text-sm font-medium text-gray-950 dark:text-white">Foto del efectivo USD en caja</figcaption>
        @if (filled($usdUrl))
            <img
                src="{{ $usdUrl }}"
                alt="Efectivo en dólares al cierre"
                class="max-h-96 w-full rounded-lg bg-gray-50 object-contain ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10"
            />
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">El cajero no cargó esta foto.</p>
        @endif
    </figure>
    <figure class="space-y-2">
        <figcaption class="text-sm font-medium text-gray-950 dark:text-white">Foto del cierre del punto de venta</figcaption>
        @if (filled($posUrl))
            <img
                src="{{ $posUrl }}"
                alt="Cierre del punto de venta"
                class="max-h-96 w-full rounded-lg bg-gray-50 object-contain ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10"
            />
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">El cajero no cargó esta foto.</p>
        @endif
    </figure>
</div>
