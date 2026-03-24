<?php

namespace App\Filament\Resources\ApiClients\Widgets;

use Filament\Widgets\Widget;

class ApiClientTokenBanner extends Widget
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.resources.api-clients.widgets.api-client-token-banner';

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public ?string $revealedPlainToken = null;
}
