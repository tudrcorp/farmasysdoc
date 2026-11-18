<ul class="grid gap-2 sm:grid-cols-2">
    @foreach ($permissions as $permission)
        <li @class([
            'flex items-start gap-3 rounded-xl px-3.5 py-3 ring-1',
            'bg-primary-50/60 ring-primary-600/15 dark:bg-primary-500/5 dark:ring-primary-400/20' => $permission['granted'],
            'bg-gray-50 ring-gray-950/5 dark:bg-white/[0.03] dark:ring-white/5' => ! $permission['granted'],
        ])>
            <span @class([
                'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full',
                'bg-primary-600 text-white' => $permission['granted'],
                'bg-gray-200 text-gray-400 dark:bg-white/10 dark:text-gray-500' => ! $permission['granted'],
            ])>
                <x-filament::icon :icon="$permission['granted'] ? 'heroicon-m-check' : 'heroicon-m-minus'" class="h-3.5 w-3.5" />
            </span>
            <span class="min-w-0">
                <span @class(['block text-sm font-semibold', 'text-gray-950 dark:text-white' => $permission['granted'], 'text-gray-400 dark:text-gray-500' => ! $permission['granted']])>{{ $permission['label'] }}</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $permission['description'] }}</span>
            </span>
        </li>
    @endforeach
</ul>
