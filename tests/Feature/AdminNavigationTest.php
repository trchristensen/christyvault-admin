<?php

use Althinect\FilamentSpatieRolesPermissions\Resources\PermissionResource;
use Althinect\FilamentSpatieRolesPermissions\Resources\RoleResource;
use App\Filament\Pages\BulkDeliveryTagProcessor;
use App\Filament\Resources\CalendarDayResource;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\DeliveryRateResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\LeaveRequestResource;
use App\Filament\Resources\LoadingProfileResource;
use App\Filament\Resources\OrderResource\Pages\DeliveryCalendar;
use App\Filament\Resources\RackTypeResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VehicleConfigurationResource;
use Filament\Facades\Filament;
use SpykApp\FilamentPasswordlessLogin\Pages\Login;

it('offers development password login while managing magic links only in admin', function (): void {
    foreach (['admin', 'team', 'maintenance', 'operations', 'sales'] as $panelId) {
        $panel = Filament::getPanel($panelId);
        $plugin = $panel->getPlugin('filament-passwordless-login');

        expect($panel->getLoginRouteAction())->toBe(Login::class)
            ->and($panel->hasPasswordReset())->toBeFalse()
            ->and($plugin->hasPasswordLoginLink())->toBeTrue()
            ->and($plugin->hasLoginAction())->toBeTrue()
            ->and($plugin->hasResource())->toBe($panelId === 'admin');
    }
});

it('compiles maintenance views and shared components into the maintenance panel theme', function (): void {
    expect(Filament::getPanel('maintenance')->getViteTheme())
        ->toBe('resources/css/filament/maintenance/theme.css');
});

it('keeps profile information without password controls', function (): void {
    foreach (['admin', 'team', 'maintenance', 'operations', 'sales'] as $panelId) {
        $panel = Filament::getPanel($panelId);
        $plugin = $panel->getPlugin('filament-breezy');
        $plugin->boot($panel);
        $components = $plugin->getRegisteredMyProfileComponents();

        expect($panel->hasProfile())->toBeFalse()
            ->and($components)->toHaveKey('personal_info')
            ->and($components)->not->toHaveKey('update_password');
    }
});

it('nests related admin navigation without changing page routes', function (): void {
    $parents = [
        CalendarDayResource::class => 'Delivery Setup',
        DeliveryRateResource::class => 'Delivery Setup',
        LoadingProfileResource::class => 'Delivery Setup',
        RackTypeResource::class => 'Delivery Setup',
        VehicleConfigurationResource::class => 'Delivery Setup',
        ContactResource::class => 'Locations',
        EmployeeResource::class => 'People',
        LeaveRequestResource::class => 'People',
        UserResource::class => 'People',
    ];

    foreach ($parents as $page => $parent) {
        expect($page::getNavigationParentItem())->toBe($parent);
    }

    expect(DeliveryCalendar::getNavigationParentItem())->toBeNull()
        ->and(DeliveryCalendar::getNavigationSort())->toBe(-100)
        ->and(BulkDeliveryTagProcessor::shouldRegisterNavigation())->toBeFalse();

    $customItems = collect(Filament::getPanel('admin')->getNavigationItems())
        ->map(fn ($item): string => $item->getLabel());

    expect($customItems)->toContain('Delivery Setup', 'People');
});

it('collapses secondary admin navigation groups by default', function (): void {
    $groups = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->keyBy(fn ($group): string => $group->getLabel());

    expect($groups['Delivery Management']->isCollapsed())->toBeFalse()
        ->and($groups['Directories']->isCollapsed())->toBeTrue()
        ->and($groups['System']->isCollapsed())->toBeTrue()
        ->and($groups)->not->toHaveKeys(['Roles and Permissions', 'Authentication', 'Switch panels']);
});

it('groups security and authentication links under system', function (): void {
    $admin = Filament::getPanel('admin');

    expect(RoleResource::getNavigationGroup())->toBe('System')
        ->and(PermissionResource::getNavigationGroup())->toBe('System')
        ->and($admin->getPlugin('filament-passwordless-login')->getNavigationGroup())->toBe('System');
});
