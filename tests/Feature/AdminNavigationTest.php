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

it('only registers magic-link management in the admin panel', function (): void {
    expect(Filament::getPanel('admin')->getPlugin('filament-passwordless-login')->hasResource())->toBeTrue()
        ->and(Filament::getPanel('team')->getPlugin('filament-passwordless-login')->hasResource())->toBeFalse()
        ->and(Filament::getPanel('maintenance')->getPlugin('filament-passwordless-login')->hasResource())->toBeFalse();
});

it('nests related admin navigation without changing page routes', function (): void {
    $parents = [
        BulkDeliveryTagProcessor::class => 'Orders',
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
        ->and(DeliveryCalendar::getNavigationSort())->toBe(-100);

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
