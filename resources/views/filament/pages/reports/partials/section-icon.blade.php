@switch ($icon)
    @case('chart-bar')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 20h18M7 16V8m5 8V5m5 11v-4" />
        </svg>
        @break
    @case('building-storefront')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V9l7-4 7 4v12M9 21v-6h6v6" />
        </svg>
        @break
    @case('truck')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16V6a1 1 0 0 1 1-1h11v11M14 16h4l2 3H3M16 16v-3m-8 3a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" />
        </svg>
        @break
    @case('users')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8 8v-1a3 3 0 0 0-2-2.83M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        @break
    @case('cube')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 8-9-5-9 5 9 5 9-5Zm0 0v8l-9 5-9-5V8" />
        </svg>
        @break
    @case('banknotes')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2 8.5h20M6 12h.01M10 12h.01M2 6.5A2 2 0 0 1 4 4.5h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-11Z" />
        </svg>
        @break
    @default
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h6v6M5 7h14v10H5V7Z" />
        </svg>
@endswitch
