<?php

namespace App\Filament\BusinessPartners\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Guía en pantalla para usuarios finales del panel aliado (pedidos, dashboard, crédito).
 */
class PartnerDocumentationPage extends Page
{
    protected static ?string $slug = 'guia-del-modulo';

    protected static ?string $title = 'Guía del panel aliado';

    protected static ?string $navigationLabel = 'Guía del módulo';

    protected static string|UnitEnum|null $navigationGroup = 'Ayuda';

    protected static ?int $navigationSort = 100;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;

    protected string $view = 'filament.business-partners.pages.partner-documentation-page';

    public bool $hasAssignedCredit = false;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $user = Filament::auth()->user();
        $this->hasAssignedCredit = $user instanceof User && $user->hasPartnerCompanyAssignedCredit();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isPartnerCompanyUser();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['fi-bp-ios-doc-page'];
    }
}
