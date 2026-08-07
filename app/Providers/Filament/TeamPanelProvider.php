<?php

namespace App\Providers\Filament;

use App\Filament\Team\Pages\Dashboard;
use App\Filament\Team\Widgets\EmployeeOverviewWidget;
use App\Filament\Team\Widgets\EquipmentCareWidget;
use App\Filament\Team\Widgets\TodaysDeliveriesWidget;
use App\Support\Filament\SharedProfile;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
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
            ->renderHook(PanelsRenderHook::HEAD_START, function (): HtmlString {
                $themeUrl = e(app(Vite::class)->asset('resources/css/filament/team/theme.css'));

                return new HtmlString(<<<HTML
                    <style>
                        html.team-theme-loading body { visibility: hidden; }
                    </style>
                    <script>
                        (() => {
                            document.documentElement.classList.add('team-theme-loading');

                            const revealTeamPanel = () => {
                                window.requestAnimationFrame(() => {
                                    document.documentElement.classList.remove('team-theme-loading');
                                });
                            };

                            window.addEventListener('DOMContentLoaded', revealTeamPanel, { once: true });
                            window.setTimeout(revealTeamPanel, 2500);
                        })();
                    </script>
                    <link rel="preload" as="style" href="{$themeUrl}" fetchpriority="high">
                    HTML);
            })
            ->colors([
                'primary' => Color::Blue,
            ])
            ->brandLogo(fn () => view('filament.logo'))
            ->brandLogoHeight('60px')
            ->discoverResources(in: app_path('Filament/Team/Resources'), for: 'App\\Filament\\Team\\Resources')
            ->discoverPages(in: app_path('Filament/Team/Pages'), for: 'App\\Filament\\Team\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->plugins([
                FilamentPasswordlessLoginPlugin::make()
                    ->showPasswordLoginLink(false)
                    ->resource(false),
                SharedProfile::make(),
                FilamentFullCalendarPlugin::make()
                    ->selectable()
                    ->editable(),
            ])
            ->discoverWidgets(in: app_path('Filament/Team/Widgets'), for: 'App\\Filament\\Team\\Widgets')
            ->widgets([
                EmployeeOverviewWidget::class,
                TodaysDeliveriesWidget::class,
                EquipmentCareWidget::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('13rem')
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
