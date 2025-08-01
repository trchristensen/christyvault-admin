<?php

namespace App\Providers\Filament;

use Althinect\FilamentSpatieRolesPermissions\FilamentSpatieRolesPermissionsPlugin;
use App\Filament\Pages\TestCalendar;
use App\Filament\Resources\OrderResource\Pages\DeliveryCalendar;
// use App\Filament\Widgets\OrderStatisticsWidget; // Removed widget
use App\Filament\Widgets\RecentOrdersWidget;
use App\Filament\Widgets\TodaysWeatherWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use App\Filament\Resources\OrderResource\Widgets\CalendarWidget;
use App\Filament\Widgets\CalendarWidget as WidgetsCalendarWidget;
use Filament\Support\Enums\MaxWidth;
use Filament\Navigation\NavigationItem;



class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->brandLogo(fn() => view('filament.logo'))
            ->brandLogoHeight('60px')
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->passwordReset()
            ->profile()
            // ->spa()
            ->colors([
                'primary' => '#1c3366',
            ])
            ->maxContentWidth(MaxWidth::Full)
            ->resources([
                config('filament-logger.activity_resource')
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
                DeliveryCalendar::class,
                \App\Filament\Pages\SystemAdmin::class,
            ])
            ->navigationGroups([
                // NavigationGroup::make()
                //     ->label('Human Resources'),
                NavigationGroup::make()
                    ->label('Delivery Management'),
                NavigationGroup::make()
                    ->label('Directories'),
                NavigationGroup::make()
                    ->label('System'),
            ])
            ->navigationItems([
                NavigationItem::make('Operations Panel')
                    ->url('/operations')
                    ->icon('heroicon-o-briefcase')
                    ->visible(fn(): bool => auth()->user()?->email === 'tchristensen@christyvault.com'),
                NavigationItem::make('Sales Panel')
                    ->url('/sales')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->visible(fn(): bool => auth()->user()?->email === 'tchristensen@christyvault.com')
            ])
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('13rem')
            // ->collapsedSidebarWidth('5rem')
            ->plugins([
                BreezyCore::make()
                    ->myProfile(),
                // FilamentSpatieRolesPermissionsPlugin::make(),
                FilamentFullCalendarPlugin::make()
                    ->plugins([
                        'dayGrid',
                        'timeGrid',
                        'multiMonth'
                    ])
                    ->config([
                        'eventDisplay' => 'block', // Force block display
                        // 'dayMaxEventRows' => 0, // Add this to prevent event rows from collapsing
                        'maxDayEvents' => false,
                        'eventMaxStack' => 0, // Prevent stacking/collapsing
                        // 'height' => 'auto', // Allow calendar to expand to fit all events
                    ])
                    ->selectable()
                    ->editable()
            ])
            // ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // TodaysWeatherWidget::class,
                // OrderStatisticsWidget::class, // Removed - basic order counts aren't useful
                // CalendarWidget::class,
                // Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
                // WidgetsCalendarWidget::class,
                // RecentOrdersWidget::class,
                // SalesStatsWidget::class,
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
            ]);
    }
}
