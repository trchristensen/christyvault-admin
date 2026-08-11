<?php

namespace App\Providers\Filament;

use App\Filament\Operations\Pages\Notifications;
use App\Filament\Operations\Widgets\InventoryStatsWidget;
use App\Filament\Operations\Widgets\LatestNotificationsWidget;
use App\Filament\Operations\Widgets\RecentPurchaseOrdersWidget;
use App\Http\Middleware\AuthenticatePanel;
use App\Support\Filament\SharedProfile;
use App\Support\FilamentLoginMode;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
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

class OperationsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('operations')
            ->path('operations')
            ->login()
            ->spa()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandLogo(fn () => view('filament.logo'))
            ->brandLogoHeight('60px')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('13rem')
            // ->collapsedSidebarWidth('5rem')
            ->plugins([
                FilamentLoginMode::configure(
                    FilamentPasswordlessLoginPlugin::make()
                        ->resource(false),
                ),
                SharedProfile::make(),
            ])
            ->discoverResources(in: app_path('Filament/Operations/Resources'), for: 'App\\Filament\\Operations\\Resources')
            ->discoverPages(in: app_path('Filament/Operations/Pages'), for: 'App\\Filament\\Operations\\Pages')
            ->pages([
                Dashboard::class,
                // Notifications::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Operations/Widgets'), for: 'App\\Filament\\Operations\\Widgets')
            ->widgets([
                InventoryStatsWidget::class,
                // LatestNotificationsWidget::class,
                RecentPurchaseOrdersWidget::class,
            ])
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
            ->authMiddleware([
                AuthenticatePanel::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s');
    }
}
