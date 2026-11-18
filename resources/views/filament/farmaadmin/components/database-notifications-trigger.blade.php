<div
    x-data="farmadocNotificationBell(@js($userKey))"
    data-unread-count="{{ (int) $unreadNotificationsCount }}"
    class="farmadoc-bell"
>
    @if ($isTopbar)
        @include('filament-panels::components.topbar.database-notifications-trigger')
    @else
        @include('filament-panels::components.sidebar.database-notifications-trigger')
    @endif
</div>
