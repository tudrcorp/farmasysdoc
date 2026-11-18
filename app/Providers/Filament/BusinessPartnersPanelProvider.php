<?php

namespace App\Providers\Filament;

use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Pages\Auth\EditProfile;
use App\Filament\BusinessPartners\Pages\Auth\Login;
use App\Filament\BusinessPartners\Pages\Auth\RequestPasswordReset;
use App\Filament\BusinessPartners\Pages\Auth\ResetPassword;
use App\Filament\BusinessPartners\Pages\Dashboard;
use App\Models\User;
use App\Support\Branding\DaleBrand;
use App\Support\Partners\PartnerPanel;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel «dale · Aliados»: lo usan los equipos de las compañías aliadas. Lleva la imagen de dale
 * (logo, Poppins y paleta) y su propio tema. Solo entran usuarios de aliados activos
 * ({@see User::canAccessPartnerPanel()}); no hay auto-registro.
 */
class BusinessPartnersPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('business-partners')
            ->path('business-partners')
            ->viteTheme('resources/css/filament/business-partners/theme.css')
            ->brandName(PartnerPanel::NAME)
            ->brandLogo(asset(DaleBrand::LOGO))
            ->darkModeBrandLogo(asset(DaleBrand::LOGO))
            ->brandLogoHeight('2.1rem')
            ->favicon(asset(DaleBrand::FAVICON))
            ->font(DaleBrand::FONT)
            ->colors([
                'primary' => DaleBrand::primary(),
                'gray' => DaleBrand::gray(),
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->authPasswordBroker(PartnerPanel::PASSWORD_BROKER)
            ->profile(EditProfile::class, isSimple: false)
            ->emailChangeVerification()
            ->spa()
            ->spaUrlExceptions(fn (): array => [route('public.api-docs')])
            ->discoverResources(in: app_path('Filament/BusinessPartners/Resources'), for: 'App\Filament\BusinessPartners\Resources')
            ->discoverPages(in: app_path('Filament/BusinessPartners/Pages'), for: 'App\Filament\BusinessPartners\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/BusinessPartners/Widgets'), for: 'App\Filament\BusinessPartners\Widgets')
            ->navigationGroups([
                NavigationGroup::make('Mi gestión'),
                NavigationGroup::make('Operaciones'),
                NavigationGroup::make('Equipo'),
                NavigationGroup::make('Ayuda')->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('Documentación API')
                    ->group('Ayuda')
                    ->icon(Heroicon::CodeBracketSquare)
                    ->url(fn (): string => route('public.api-docs'), shouldOpenInNewTab: true)
                    ->visible(fn (): bool => PartnerPanel::can(PartnerPanelPermission::ApiDocs))
                    ->sort(110),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->maxContentWidth(Width::Full)
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->sidebarCollapsibleOnDesktop()
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
