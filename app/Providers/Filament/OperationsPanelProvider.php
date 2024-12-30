<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\NavigationItem;
use App\Filament\Operations\Pages\Notifications;
use App\Livewire\NotificationsDropdown;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Livewire\Livewire;
use App\Filament\Operations\Widgets\InventoryStatsWidget;
use App\Filament\Operations\Widgets\LatestNotificationsWidget;
use App\Filament\Operations\Widgets\RecentPurchaseOrdersWidget;

class OperationsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('operations')
            ->path('operations')
            ->login()
            ->passwordReset()
            ->profile()
            ->spa()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandLogo('images/logo.svg')
            ->brandLogoHeight('60px')
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('13rem')
            // ->collapsedSidebarWidth('5rem')
            ->plugins([
                BreezyCore::make()
                    ->myProfile(),
            ])
            ->navigationItems([
                NavigationItem::make('Admin Panel')
                    ->url('/')
                    ->icon('heroicon-o-building-office')
            ])

            ->discoverResources(in: app_path('Filament/Operations/Resources'), for: 'App\\Filament\\Operations\\Resources')
            ->discoverPages(in: app_path('Filament/Operations/Pages'), for: 'App\\Filament\\Operations\\Pages')
            ->pages([
                Pages\Dashboard::class,
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
                Authenticate::class,
            ])
            ->databaseNotifications();
    }
}
