<?php

namespace App\Providers\Filament;

use App\Filament\BusinessPartners\Widgets\PartnerAssignedCreditWidget;
use App\Filament\BusinessPartners\Widgets\StatsSatatusOrderOverview;
use App\Filament\BusinessPartners\Widgets\TotalOrderForMonthChart;
use App\Filament\GlobalSearch\FarmaadminGlobalSearchProvider;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\EmailVerificationPrompt;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use App\Filament\Pages\Auth\PasswordReset\ResetPassword;
use App\Filament\Pages\Auth\Register;
use App\Filament\Widgets\FarmaadminAccountWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class BusinessPartnersPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('business-partners')
            ->path('business-partners')
            ->viteTheme('resources/css/filament/farmaadmin/theme.css')
            ->font('League Spartan')
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->profile(EditProfile::class)
            ->spa()
            ->emailVerification(EmailVerificationPrompt::class)
            ->emailChangeVerification()
            ->favicon(asset('images/logos/favicon.png'))
            ->brandLogo(asset('images/logos/farmadoc-ligth.png'))
            ->darkModeBrandLogo(asset('images/logos/farmadoc-dark.png'))
            ->brandLogoHeight('4.6rem')
            ->colors([
                'primary' => Color::hex('#FCE422'),
                'info' => Color::hex('#18ACB2'),
                'success' => Color::hex('#0E949A'),
            ])
            ->discoverResources(in: app_path('Filament/BusinessPartners/Resources'), for: 'App\Filament\BusinessPartners\Resources')
            ->discoverPages(in: app_path('Filament/BusinessPartners/Pages'), for: 'App\Filament\BusinessPartners\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/BusinessPartners/Widgets'), for: 'App\Filament\BusinessPartners\Widgets')
            ->widgets([
                FarmaadminAccountWidget::class,
                PartnerAssignedCreditWidget::class,
                StatsSatatusOrderOverview::class,
                TotalOrderForMonthChart::class,
                // AccountWidget::class,
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
            ]);
    }
}
