<?php

namespace App\Filament\Pages;

use App\Models\FiscalCompanySetting;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class ManageFiscalCompanySettings extends Page
{
    protected static ?string $title = 'Datos fiscales de la empresa';

    protected static ?string $navigationLabel = 'Datos fiscales de la empresa';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice2;

    protected static ?string $slug = 'datos-fiscales-empresa';

    protected string $view = 'filament.pages.manage-fiscal-company-settings';

    public string $address = '';

    public function mount(): void
    {
        $this->address = (string) (FiscalCompanySetting::current()->address ?? '');
    }

    public function getHeading(): string|Htmlable
    {
        return 'Datos fiscales de la empresa';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Dirección de la empresa principal. Se imprime en las facturas fiscales y en los comprobantes de retención.';
    }

    public function save(): void
    {
        $this->validate([
            'address' => ['nullable', 'string', 'max:2000'],
        ], [
            'address.max' => 'La dirección fiscal no puede superar los 2000 caracteres.',
        ]);

        $address = trim($this->address);
        $setting = FiscalCompanySetting::current();
        $setting->address = $address !== '' ? $address : null;
        $setting->save();

        $this->address = (string) ($setting->address ?? '');

        Notification::make()
            ->title('Dirección fiscal guardada')
            ->body('La dirección de la empresa principal se usará en las facturas fiscales.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessFarmaadminMenuKey('fiscal_company_settings');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
