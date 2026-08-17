<?php

use App\Filament\Team\Pages\Schedule;
use App\Filament\Team\Widgets\EmployeeOverviewWidget;
use App\Filament\Team\Widgets\TodaysDeliveriesWidget;
use App\Models\User;
use App\Notifications\MaintenanceRequestSubmitted;
use App\Support\PanelSwitcher;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

it('registers the panel dropdown at the top of every sidebar', function (): void {
    expect(FilamentView::hasRenderHook(PanelsRenderHook::SIDEBAR_NAV_START))->toBeTrue();
});

it('loads top-alignment rules for Filament modals in every panel', function (): void {
    $modalStylesheet = collect(FilamentAsset::getStyles(['app']))
        ->first(fn ($asset): bool => $asset->getId() === 'filament-modal-positioning');

    expect($modalStylesheet)->not->toBeNull();

    $css = file_get_contents($modalStylesheet->getPath());

    expect($css)
        ->toContain('.fi-modal:not(.fi-modal-slide-over) > .fi-modal-window-ctn')
        ->toContain('grid-template-rows: auto minmax(0, 1fr) !important;')
        ->toContain('.fi-modal:not(.fi-modal-slide-over) > .fi-modal-window-ctn > .fi-modal-window')
        ->toContain('grid-row-start: 1 !important;');
});

it('enables database notifications in the team panel', function (): void {
    $panel = Filament::getPanel('team');

    expect($panel->hasDatabaseNotifications())->toBeTrue()
        ->and($panel->getDatabaseNotificationsPollingInterval())->toBe('30s');
});

it('matches the admin sidebar size and desktop collapse behavior', function (): void {
    $adminPanel = Filament::getPanel('admin');
    $teamPanel = Filament::getPanel('team');

    expect($teamPanel->isSidebarCollapsibleOnDesktop())->toBeTrue()
        ->and($teamPanel->getSidebarWidth())->toBe($adminPanel->getSidebarWidth())
        ->and($teamPanel->getSidebarWidth())->toBe('13rem');
});

it('preloads the team theme and guards its initial paint', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('employee', 'web'));

    $response = $this->actingAs($user)->get('/team');
    $html = $response->getContent();

    $response
        ->assertOk()
        ->assertSee('Welcome, '.str($user->name)->before(' '))
        ->assertSee('Upcoming Company Dates')
        ->assertSee('My Time Off')
        ->assertSee('team-employee-overview-grid', false)
        ->assertDontSee('Request time off')
        ->assertDontSee("Today's Deliveries");
    expect($html)
        ->toContain('rel="preload"')
        ->toContain(app(Vite::class)->asset('resources/css/filament/team/theme.css'))
        ->toContain('team-theme-loading')
        ->toContain('DOMContentLoaded');
});

it('shows the employee overview on the team dashboard', function (): void {
    expect(Filament::getPanel('team')->getWidgets())
        ->toContain(EmployeeOverviewWidget::class);
});

it('keeps the delivery schedule hidden from ordinary employees even with an accidental permission grant', function (): void {
    $permission = Permission::findOrCreate('view team delivery schedule', 'web');
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('employee', 'web'));
    $user->givePermissionTo($permission);

    $this->actingAs($user);

    expect($user->can('view team delivery schedule'))->toBeTrue()
        ->and($user->canViewTeamDeliverySchedule())->toBeFalse()
        ->and(Schedule::canAccess())->toBeFalse()
        ->and(TodaysDeliveriesWidget::canView())->toBeFalse();

    $this->get('/team/schedule')->assertForbidden();
});

it('allows authorized delivery roles to see the team schedule', function (): void {
    $permission = Permission::findOrCreate('view team delivery schedule', 'web');
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('driver', 'web'));
    $user->givePermissionTo($permission);

    $this->actingAs($user);

    expect($user->canViewTeamDeliverySchedule())->toBeTrue()
        ->and(Schedule::canAccess())->toBeTrue()
        ->and(TodaysDeliveriesWidget::canView())->toBeTrue();
});

it('only moves team dashboard deliveries ahead of employee content for drivers on mobile', function (): void {
    $permission = Permission::findOrCreate('view team delivery schedule', 'web');
    $driver = User::factory()->create();
    $driver->assignRole(Role::findOrCreate('driver', 'web'));
    $driver->givePermissionTo($permission);

    $foreman = User::factory()->create();
    $foreman->assignRole(Role::findOrCreate('foreman', 'web'));
    $foreman->givePermissionTo($permission);

    $this->actingAs($driver)
        ->get('/team')
        ->assertOk()
        ->assertSee('team-dashboard-deliveries-widget--driver-first', false);

    $this->actingAs($foreman)
        ->get('/team')
        ->assertOk()
        ->assertSee("Today's Deliveries")
        ->assertDontSee('team-dashboard-deliveries-widget--driver-first', false);
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
