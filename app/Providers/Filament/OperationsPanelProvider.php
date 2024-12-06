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
use Illuminate\Support\Facades\Blade;

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
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandLogo('https://christyvault.com/_next/static/media/logo.22a652dc.svg')
            ->brandLogoHeight('60px')
            ->navigationItems([
                NavigationItem::make('Notifications')
                    ->icon('heroicon-o-bell')
                    ->badge(fn () => auth()->user()->unreadNotifications->count() ?: null)
                    ->url(fn () => '/operations/notifications')
            ])
            // ->livewireComponents([
            //     'notifications-dropdown' => NotificationsDropdown::class,
            // ])
            ->discoverResources(in: app_path('Filament/Operations/Resources'), for: 'App\\Filament\\Operations\\Resources')
            ->discoverPages(in: app_path('Filament/Operations/Pages'), for: 'App\\Filament\\Operations\\Pages')
            ->pages([
                Pages\Dashboard::class,
                Notifications::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Operations/Widgets'), for: 'App\\Filament\\Operations\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
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
