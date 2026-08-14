<?php

namespace App\Providers\Filament;

use Althinect\FilamentSpatieRolesPermissions\FilamentSpatieRolesPermissionsPlugin;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SystemAdmin;
use App\Filament\Resources\OrderResource\Pages\DeliveryCalendar;
use App\Filament\Widgets\OfficeManagerAttentionWidget;
use App\Filament\Widgets\PlanningOpportunitiesWidget;
use App\Filament\Widgets\TodayTomorrowBriefingWidget;
use App\Filament\Widgets\WeeklyDeliveryWeatherWidget;
use App\Http\Middleware\AuthenticatePanel;
use App\Support\Filament\SharedProfile;
use App\Support\FilamentLoginMode;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use SpykApp\FilamentPasswordlessLogin\FilamentPasswordlessLoginPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->brandLogo(fn () => view('filament.logo'))
            ->brandLogoHeight('60px')
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            // ->spa()
            ->colors([
                // 'primary' => '#1c3366',
                'primary' => Color::generateV3Palette('#1c3366'),
            ])
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                DeliveryCalendar::class,
                SystemAdmin::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Delivery Management')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Directories')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('System')
                    ->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('Delivery Setup')
                    ->group('Delivery Management')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->sort(40),
                NavigationItem::make('People')
                    ->group('Directories')
                    ->icon('heroicon-o-user-group')
                    ->sort(20),
            ])
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('13rem')
            // ->collapsedSidebarWidth('5rem')
            ->plugins([
                FilamentLoginMode::configure(
                    FilamentPasswordlessLoginPlugin::make()
                        ->navigationGroup('System'),
                ),
                FilamentSpatieRolesPermissionsPlugin::make(),
                SharedProfile::make(),
                // FilamentSpatieRolesPermissionsPlugin::make(),
                FilamentFullCalendarPlugin::make()
                    ->plugins([
                        'dayGrid',
                        'timeGrid',
                        'multiMonth',
                    ])
                    ->config([
                        'eventDisplay' => 'block', // Force block display
                        // 'dayMaxEventRows' => 0, // Add this to prevent event rows from collapsing
                        'maxDayEvents' => false,
                        'eventMaxStack' => 0, // Prevent stacking/collapsing
                        // 'height' => 'auto', // Allow calendar to expand to fit all events
                    ])
                    ->selectable()
                    ->editable(),
            ])
            // ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                OfficeManagerAttentionWidget::class,
                WeeklyDeliveryWeatherWidget::class,
                TodayTomorrowBriefingWidget::class,
                PlanningOpportunitiesWidget::class,
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
            ]);
    }
}
