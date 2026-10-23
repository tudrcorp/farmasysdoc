<div class="ep-nav ep-mobile-only">
    <a href="{{ $backHref }}" class="ep-ghost" wire:navigate>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
        Atrás
    </a>
    <p class="ep-step">{{ $step }}</p>
    @include('employee-portal.partials.menu-button')
</div>
