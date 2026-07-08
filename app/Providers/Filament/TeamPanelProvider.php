<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use App\Filament\Team\Widgets\TodaysDeliveriesWidget;
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
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use SpykApp\FilamentPasswordlessLogin\FilamentPasswordlessLoginPlugin;

class TeamPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('team')
            ->login()
            ->path('team')
            ->viteTheme('resources/css/filament/team/theme.css')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->brandLogo(fn() => view('filament.logo'))
            ->brandLogoHeight('60px')
            ->discoverResources(in: app_path('Filament/Team/Resources'), for: 'App\\Filament\\Team\\Resources')
            ->discoverPages(in: app_path('Filament/Team/Pages'), for: 'App\\Filament\\Team\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->plugins([
                FilamentPasswordlessLoginPlugin::make(),
                FilamentFullCalendarPlugin::make()
                    ->selectable()
                    ->editable()
            ])
            ->discoverWidgets(in: app_path('Filament/Team/Widgets'), for: 'App\\Filament\\Team\\Widgets')
            ->widgets([
                TodaysDeliveriesWidget::class,
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
