<?php

namespace App\Livewire\Filament;

use App\Services\Dolar\DolarApiDolaresService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
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
        $this->rateDisplay = Cache::remember(
            'farmadoc.bcv_rate_badge.display',
            120,
            function (): ?string {
                $payload = app(DolarApiDolaresService::class)->getOfficialUsdToVesRatePayload();

                return $payload['display'] ?? null;
            },
        );
    }

    public function render(): View
    {
        return view('livewire.filament.bcv-exchange-rate-badge', [
            'logoUrl' => asset('images/logos/logoBCV.png'),
        ]);
    }
}
