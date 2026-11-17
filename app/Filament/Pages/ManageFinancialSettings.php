<?php

namespace App\Filament\Pages;

use App\Models\FinancialSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
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

    public bool $vatRetentionAgentActive = true;

    public ?string $vatRetentionSuspendedFrom = null;

    public ?string $vatRetentionResumedFrom = null;

    public function mount(): void
    {
        $row = FinancialSetting::current();
        $this->defaultVatRatePercent = (float) $row->default_vat_rate_percent;
        $this->igtfRatePercent = (float) ($row->igtf_rate_percent ?? 3);
        $this->vatRetentionAgentActive = (bool) ($row->vat_retention_agent_active ?? true);
        $this->vatRetentionSuspendedFrom = $row->vat_retention_suspended_from?->toDateString();
        $this->vatRetentionResumedFrom = $row->vat_retention_resumed_from?->toDateString();
    }

    public function canEditVatRetentionAgent(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Administración financiera';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'IVA por defecto (productos que gravan), IGTF para cobros en efectivo USD y agente de retención de IVA.';
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

        if ($this->canEditVatRetentionAgent()) {
            $this->validateVatRetentionAgent();
            $this->fillVatRetentionAgent($setting);
        }

        $setting->save();

        Notification::make()
            ->title('Parámetros guardados')
            ->body('IVA por defecto: '.$vat.'%. IGTF (USD efectivo): '.$igtf.'%.')
            ->success()
            ->send();
    }

    private function validateVatRetentionAgent(): void
    {
        $this->vatRetentionSuspendedFrom = filled($this->vatRetentionSuspendedFrom) ? $this->vatRetentionSuspendedFrom : null;
        $this->vatRetentionResumedFrom = filled($this->vatRetentionResumedFrom) ? $this->vatRetentionResumedFrom : null;

        if (! $this->vatRetentionAgentActive) {
            $this->validate(
                ['vatRetentionSuspendedFrom' => ['required', 'date']],
                ['vatRetentionSuspendedFrom.required' => 'Indique la fecha de factura desde la que no se retiene IVA.'],
            );

            return;
        }

        if ($this->vatRetentionSuspendedFrom === null) {
            $this->vatRetentionResumedFrom = null;

            return;
        }

        $this->validate(
            [
                'vatRetentionSuspendedFrom' => ['required', 'date'],
                'vatRetentionResumedFrom' => ['required', 'date', 'after:vatRetentionSuspendedFrom'],
            ],
            [
                'vatRetentionResumedFrom.required' => 'Indique la fecha de factura desde la que se vuelve a retener IVA.',
                'vatRetentionResumedFrom.after' => 'La fecha de reactivación debe ser posterior a la fecha «Sin retención desde».',
            ],
        );
    }

    private function fillVatRetentionAgent(FinancialSetting $setting): void
    {
        $setting->vat_retention_agent_active = $this->vatRetentionAgentActive;
        $setting->vat_retention_suspended_from = $this->vatRetentionSuspendedFrom;
        $setting->vat_retention_resumed_from = $this->vatRetentionAgentActive ? $this->vatRetentionResumedFrom : null;

        if (! $this->vatRetentionAgentActive) {
            $this->vatRetentionResumedFrom = null;
        }

        if (! $setting->isDirty(['vat_retention_agent_active', 'vat_retention_suspended_from', 'vat_retention_resumed_from'])) {
            return;
        }

        AuditLogger::forModel($setting, 'vat_retention_agent_changed', [
            'antes' => [
                'agente_retencion' => (bool) $setting->getOriginal('vat_retention_agent_active'),
                'sin_retencion_desde' => $setting->getOriginal('vat_retention_suspended_from')?->toDateString(),
                'retener_de_nuevo_desde' => $setting->getOriginal('vat_retention_resumed_from')?->toDateString(),
            ],
            'despues' => [
                'agente_retencion' => $this->vatRetentionAgentActive,
                'sin_retencion_desde' => $this->vatRetentionSuspendedFrom,
                'retener_de_nuevo_desde' => $setting->vat_retention_resumed_from?->toDateString(),
            ],
        ]);
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessFarmaadminMenuKey('financial_settings');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
