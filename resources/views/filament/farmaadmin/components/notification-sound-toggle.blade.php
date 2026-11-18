<div x-data="farmadocNotificationMute" class="farmadoc-notification-mute">
    <div x-show="! muted">
        <x-filament::icon-button
            :icon="\Filament\Support\Icons\Heroicon::OutlinedSpeakerWave"
            color="gray"
            icon-size="lg"
            label="Silenciar sonido de notificaciones"
            tooltip="Silenciar sonido de notificaciones"
            x-on:click="toggle()"
        />
    </div>

    <div x-show="muted" x-cloak>
        <x-filament::icon-button
            :icon="\Filament\Support\Icons\Heroicon::OutlinedSpeakerXMark"
            color="gray"
            icon-size="lg"
            label="Activar sonido de notificaciones"
            tooltip="Activar sonido de notificaciones"
            x-on:click="toggle()"
        />
    </div>
</div>
