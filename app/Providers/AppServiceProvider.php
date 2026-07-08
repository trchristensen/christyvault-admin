<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use Livewire\Livewire;
// use App\Livewire\NotificationsDropdown;
use App\Models\PurchaseOrder;
use App\Models\Location;
use App\Observers\PurchaseOrderObserver;
use App\Observers\LocationObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use SpykApp\PasswordlessLogin\Models\MagicLoginToken;
use App\Policies\MagicLoginTokenPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        // Super admins bypass all policy and permission checks.
        Gate::before(fn ($user) => $user->hasRole('super-admin') ? true : null);

        // This vendor model cannot rely on application policy auto-discovery.
        Gate::policy(MagicLoginToken::class, MagicLoginTokenPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

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

        // Livewire::component('notifications-dropdown', NotificationsDropdown::class);

        PurchaseOrder::observe(PurchaseOrderObserver::class);
        Location::observe(LocationObserver::class);
        Order::observe(OrderObserver::class);
    }
}
