<div class="min-w-0">
    <p class="truncate font-mono text-sm font-medium text-gray-900 dark:text-gray-100">{{ $order['number'] }}</p>
    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $order['created_at'] }}@if (filled($order['detail'] ?? null)) · {{ $order['detail'] }}@endif</p>
</div>
<div class="flex shrink-0 items-center gap-3">
    <span class="dale-figures text-sm font-semibold text-gray-950 dark:text-white">{{ $money($order['total']) }}</span>
    <span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $statusChip[$order['status']] ?? 'bg-gray-50 text-gray-600 ring-gray-600/20' }}">{{ $order['label'] }}</span>
</div>
