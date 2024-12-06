<?php

namespace App\Providers;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use Livewire\Livewire;
use App\Livewire\NotificationsDropdown;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LogViewer::auth(function ($request) {
            config(['sanctum.stateful' => array_merge(
                config('sanctum.stateful', []),
                [parse_url(env('APP_URL'), PHP_URL_HOST)]
            )]);

            return $request->user()
                && in_array($request->user()->email, [
                    'tchristensen@christyvault.com'
                ]);
        });

        FilamentAsset::register([
            Css::make('calendar-styles', resource_path('css/calendar.css')),
        ]);

        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): string => Livewire::mount('notifications-dropdown')
        );
    }
}
