<?php

namespace App\Filament\Pages;

use App\Models\FinancialSetting;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class ManageFinancialSettings extends Page
{
    protected static ?string $title = 'Administración financiera';

    protected static ?string $navigationLabel = 'Administración financiera';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 41;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Calculator;

    protected static ?string $slug = 'administracion-financiera';

    protected string $view = 'filament.pages.manage-financial-settings';

    public float|string $defaultVatRatePercent = 16;

    public float|string $igtfRatePercent = 3;

    public function mount(): void
    {
        $row = FinancialSetting::current();
        $this->defaultVatRatePercent = (float) $row->default_vat_rate_percent;
        $this->igtfRatePercent = (float) ($row->igtf_rate_percent ?? 3);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Administración financiera';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'IVA por defecto (productos que gravan) e IGTF para cobros en efectivo USD.';
    }

    public function save(): void
    {
        $this->validate([
            'defaultVatRatePercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'igtfRatePercent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $vat = round((float) $this->defaultVatRatePercent, 2);
        $igtf = round((float) $this->igtfRatePercent, 2);

        $setting = FinancialSetting::current();
        $setting->default_vat_rate_percent = $vat;
        $setting->igtf_rate_percent = $igtf;
        $setting->save();

        Notification::make()
            ->title('Parámetros guardados')
            ->body('IVA por defecto: '.$vat.'%. IGTF (USD efectivo): '.$igtf.'%.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
