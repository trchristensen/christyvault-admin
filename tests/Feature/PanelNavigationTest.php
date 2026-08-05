<?php

use App\Models\User;
use App\Notifications\MaintenanceRequestSubmitted;
use App\Support\PanelSwitcher;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

it('registers the panel dropdown at the top of every sidebar', function (): void {
    expect(FilamentView::hasRenderHook(PanelsRenderHook::SIDEBAR_NAV_START))->toBeTrue();
});

it('enables database notifications in the team panel', function (): void {
    $panel = Filament::getPanel('team');

    expect($panel->hasDatabaseNotifications())->toBeTrue()
        ->and($panel->getDatabaseNotificationsPollingInterval())->toBe('30s');
});

it('shows administrators every other panel in the panel switcher', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $this->actingAs($user);

    $visibleLabels = collect(PanelSwitcher::options('admin'))
        ->pluck('label')
        ->values()
        ->all();

    expect($visibleLabels)->toBe([
        'Operations Panel',
        'Sales Panel',
        'Team Panel',
        'Maintenance Panel',
    ]);
});

it('identifies the current panel for the dropdown trigger', function (): void {
    expect(PanelSwitcher::current('maintenance'))->toMatchArray([
        'id' => 'maintenance',
        'label' => 'Maintenance Panel',
        'url' => '/maintenance',
        'icon' => 'heroicon-o-wrench-screwdriver',
    ]);
});

it('only shows panel destinations the user can access', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('sales', 'web'));

    $this->actingAs($user);

    $visibleLabels = collect(PanelSwitcher::options('admin'))
        ->pluck('label')
        ->values()
        ->all();

    expect($visibleLabels)->toBe(['Sales Panel'])
        ->and($user->canAccessPanelById('sales'))->toBeTrue()
        ->and($user->canAccessPanelById('operations'))->toBeFalse()
        ->and($user->canAccessPanelById('unknown'))->toBeFalse();
});

it('shows unread notification counts on their destination panels', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => MaintenanceRequestSubmitted::class,
        'data' => ['title' => 'Existing maintenance notification'],
    ]);

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\FutureSalesNotification',
        'data' => ['panel' => 'sales', 'title' => 'Sales notification'],
    ]);

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\ReadMaintenanceNotification',
        'data' => ['panel' => 'maintenance', 'title' => 'Read maintenance notification'],
        'read_at' => now(),
    ]);

    $this->actingAs($user);

    $items = collect(PanelSwitcher::options('admin'))->keyBy('label');

    expect($items['Maintenance Panel']['unread'])->toBe(1)
        ->and($items['Sales Panel']['unread'])->toBe(1)
        ->and($items['Operations Panel']['unread'])->toBe(0)
        ->and($items['Team Panel']['unread'])->toBe(0);
});

it('renders the authorized destinations inside the dropdown', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    $html = view('filament.components.panel-switcher')->render();

    expect($html)->toContain('fi-panel-switcher')
        ->and($html)->toContain('fi-input-wrp')
        ->and($html)->toContain('fi-select-input-btn')
        ->and($html)->toContain('width: calc(100% + 1rem)')
        ->and($html)->toContain('margin-bottom: -1rem')
        ->and($html)->toContain('Admin Panel')
        ->and($html)->toContain('Operations Panel')
        ->and($html)->toContain('Sales Panel')
        ->and($html)->toContain('Team Panel')
        ->and($html)->toContain('Maintenance Panel');
});
