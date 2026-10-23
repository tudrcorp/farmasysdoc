<div class="ep-header-tools">
    <a
        href="{{ route('employee-portal.requests') }}"
        class="ep-bell"
        wire:navigate
        x-data
        x-bind:class="{ 'has-unread': $store.epNotifications.count > 0 }"
        x-bind:aria-label="$store.epNotifications.count > 0 ? `Tienes ${$store.epNotifications.count} avisos nuevos` : 'Mis solicitudes'"
        aria-label="Mis solicitudes"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" class="size-5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        <span
            class="ep-bell-badge"
            x-show="$store.epNotifications.count > 0"
            x-cloak
            x-text="$store.epNotifications.count > 9 ? '9+' : $store.epNotifications.count"
        ></span>
    </a>
    <button
        type="button"
        class="ep-menu-trigger"
        @click="toggle()"
        :class="{ 'is-open': menuOpen }"
        :aria-expanded="menuOpen.toString()"
        :aria-label="menuOpen ? 'Cerrar menú' : 'Abrir menú'"
        aria-controls="ep-portal-menu"
    >
        <span class="ep-menu-trigger-icon" :class="{ 'is-open': menuOpen }" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </span>
    </button>
</div>
