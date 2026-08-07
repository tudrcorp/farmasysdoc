<?php

namespace App\Livewire\Filament;

use App\Services\Dolar\DolarApiDolaresService;
use App\Services\Dolar\DolarApiEstadoService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class BcvExchangeRateBadge extends Component
{
    public ?string $rateDisplay = null;

    public function mount(): void
    {
        $this->refreshRate();
    }

    public function refreshRate(): void
    {
        if (! app(DolarApiEstadoService::class)->isAvailable()) {
            $this->rateDisplay = null;

            return;
        }

        $payload = app(DolarApiDolaresService::class)->getOfficialUsdToVesRatePayload();

        if ($payload === null) {
            $this->rateDisplay = null;

            return;
        }

        $this->rateDisplay = $payload['display'];
    }

    public function render(): View
    {
        return view('livewire.filament.bcv-exchange-rate-badge', [
            'logoUrl' => asset('images/logos/logoBCV.png'),
        ]);
    }
}
