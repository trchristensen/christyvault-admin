<?php

namespace App\Providers\Filament;

use App\Filament\Maintenance\Pages\Dashboard;
use App\Filament\Maintenance\Widgets\AssetReliabilityWidget;
use App\Filament\Maintenance\Widgets\MaintenanceStatsWidget;
use App\Filament\Maintenance\Widgets\OpenWorkOrdersWidget;
use App\Support\Filament\SharedProfile;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use SpykApp\FilamentPasswordlessLogin\FilamentPasswordlessLoginPlugin;

class MaintenancePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('maintenance')
            ->path('maintenance')
            ->login()
            ->spa()
            ->brandLogo(fn () => view('filament.logo'))
            ->brandLogoHeight('60px')
            ->colors(['primary' => Color::Orange])
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('15rem')
            ->discoverResources(
                in: app_path('Filament/Maintenance/Resources'),
                for: 'App\\Filament\\Maintenance\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Maintenance/Pages'),
                for: 'App\\Filament\\Maintenance\\Pages',
            )
            ->pages([Dashboard::class])
            ->discoverWidgets(
                in: app_path('Filament/Maintenance/Widgets'),
                for: 'App\\Filament\\Maintenance\\Widgets',
            )
            ->widgets([MaintenanceStatsWidget::class, OpenWorkOrdersWidget::class, AssetReliabilityWidget::class])
            ->plugins([
                FilamentPasswordlessLoginPlugin::make()
                    ->showPasswordLoginLink(false)
                    ->resource(false),
                SharedProfile::make(),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
