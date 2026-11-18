<?php

namespace App\Livewire\Filament;

use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Contracts\View\View;

/**
 * Campana de notificaciones del panel con aviso perceptible: se sacude mientras haya avisos sin leer
 * y, cuando llega uno nuevo, suena un «ding» y vibra (en equipos que lo permiten).
 */
class FarmadocDatabaseNotifications extends DatabaseNotifications
{
    public function getTrigger(): ?View
    {
        $isTopbar = ($this->position ?? filament()->getDatabaseNotificationsPosition()) === DatabaseNotificationsPosition::Topbar;

        return view('filament.farmaadmin.components.database-notifications-trigger', [
            'isTopbar' => $isTopbar,
            'userKey' => (string) ($this->getUser()?->getAuthIdentifier() ?? 'guest'),
        ]);
    }
}
