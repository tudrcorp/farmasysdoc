<?php

namespace App\Livewire\Filament;

use App\Filament\Concerns\InteractsWithBdvPagomovilConciliationForm;
use App\Filament\Resources\ConciliationBdvs\ConciliationBdvResource;
use App\Models\User;
use App\Services\BdvConciliation\BdvPagomovilConciliationService;
use App\Support\Sales\PosPaymentMethodOptions;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class BdvPagomovilConciliationFab extends Component
{
    use InteractsWithBdvPagomovilConciliationForm;

    public bool $hideFab = false;

    public bool $sheetOpen = false;

    public function mount(BdvPagomovilConciliationService $service): void
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->canAccessFarmaadminMenuKey('bdv_pagomovil_conciliation')) {
            $this->hideFab = true;

            return;
        }

        $this->hideFab = request()->routeIs('filament.farmaadmin.pages.conciliacion-pagomovil-bdv');

        $this->bootBdvPagomovilConciliationForm($service);
    }

    public function openSheet(): void
    {
        $this->sheetOpen = true;
        $this->resetValidation();
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
    }

    public function resetForm(BdvPagomovilConciliationService $service): void
    {
        $this->resetBdvPagomovilConciliationForm($service);
        $this->dispatch('bdv-pm-sheet-reset');
    }

    public function render(): View
    {
        return view('livewire.filament.bdv-pagomovil-conciliation-fab', [
            'logoUrl' => PosPaymentMethodOptions::bdvNavigationIconUrl(),
            'fullLogoUrl' => asset('images/logos/bdv-banco-de-venezuela.png'),
            'canViewHistory' => ConciliationBdvResource::canViewAny(),
            'conciliationsUrl' => ConciliationBdvResource::getUrl('index'),
        ]);
    }
}
