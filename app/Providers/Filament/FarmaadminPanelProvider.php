<?php

namespace App\Providers\Filament;

use App\Filament\GlobalSearch\FarmaadminGlobalSearchProvider;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\FarmaadminDashboard;
use App\Filament\Pages\Marketing\MarketingHubPage;
use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Filament\Widgets\FarmaadminAccountWidget;
use App\Http\Middleware\EnsureFarmaadminMenuAccess;
use App\Models\User;
use App\Support\Filament\FarmaadminDeliveryUserAccess;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class FarmaadminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('farmaadmin')
            ->path('farmaadmin')
            ->viteTheme('resources/css/filament/farmaadmin/theme.css')
            ->font('League Spartan')
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset()
            ->profile()
            ->spa()
            ->emailVerification()
            ->emailChangeVerification()
            ->emailVerification()
            ->favicon(asset('images/logos/favicon.png'))
            ->brandLogo(asset('images/logos/farmadoc-ligth.png'))
            ->darkModeBrandLogo(asset('images/logos/farmadoc-dark.png'))
            ->brandLogoHeight('4.6rem')
            ->colors([
                'primary' => Color::hex('#FCE422'),
                'info' => Color::hex('#18ACB2'),
                'success' => Color::hex('#0E949A'),
            ])
            ->navigationGroups([
                'configuration' => NavigationGroup::make('Configuración'),
                'operations' => NavigationGroup::make(function (): string {
                    $user = request()->user();

                    return $user instanceof User
                        ? $user->navigationOperationsGroupLabel()
                        : 'Farmadoc®';
                }),
                'marketing' => NavigationGroup::make('Marketing'),
                'inventory' => NavigationGroup::make('Inventario'),
                'commercial_allies' => NavigationGroup::make('Aliados Comerciales'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                MarketingHubPage::class,
            ])
            ->homeUrl(function (): string {
                if (FarmaadminDeliveryUserAccess::isRestrictedDeliveryUser()) {
                    return DeliveryResource::getUrl(panel: 'farmaadmin', isAbsolute: false);
                }

                return FarmaadminDashboard::getUrl(panel: 'farmaadmin', isAbsolute: false);
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                FarmaadminAccountWidget::class,
                // FilamentInfoWidget::class,
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
            ->globalSearch(FarmaadminGlobalSearchProvider::class)
            ->globalSearchDebounce('250ms')
            ->globalSearchFieldKeyBindingSuffix()
            ->authMiddleware([
                Authenticate::class,
                EnsureFarmaadminMenuAccess::class,
            ]);
    }
}
