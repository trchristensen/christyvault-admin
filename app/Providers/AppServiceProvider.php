<?php

namespace App\Providers;

use App\Models\Location;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Observers\LocationObserver;
use App\Observers\OrderObserver;
use App\Observers\PurchaseOrderObserver;
// use App\Livewire\NotificationsDropdown;
use App\Policies\ActivityPolicy;
use App\Policies\MagicLoginTokenPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use SpykApp\PasswordlessLogin\Models\MagicLoginToken;

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
        Gate::before(fn($user) => $user->hasRole('super-admin') ? true : null);

        // This vendor model cannot rely on application policy auto-discovery.
        Gate::policy(MagicLoginToken::class, MagicLoginTokenPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        Gate::define('viewLogViewer', fn(User $user): bool => $user->hasRole('super-admin'));

        FilamentAsset::register([
            Css::make('calendar-styles', resource_path('css/calendar.css')),
        ]);

        // Livewire::component('notifications-dropdown', NotificationsDropdown::class);

        PurchaseOrder::observe(PurchaseOrderObserver::class);
        Location::observe(LocationObserver::class);
        Order::observe(OrderObserver::class);
    }
}
