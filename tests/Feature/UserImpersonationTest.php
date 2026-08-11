<?php

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Facades\Impersonation;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('only lets super admins impersonate eligible users', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin', 'web'));

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));

    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('employee', 'web'));

    expect($superAdmin->canImpersonate())->toBeTrue()
        ->and($admin->canImpersonate())->toBeFalse()
        ->and($employee->canBeImpersonated())->toBeTrue()
        ->and($superAdmin->canBeImpersonated())->toBeFalse();

    $this->actingAs($superAdmin);

    Livewire::test(ListUsers::class)
        ->assertTableActionVisible('impersonate', $employee)
        ->assertTableActionHidden('impersonate', $superAdmin);
});

it('hides the impersonation action from ordinary admins', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));

    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('employee', 'web'));

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('impersonate', $employee);
});

it('starts impersonation from the users table and redirects to the target panel', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin', 'web'));

    $technician = User::factory()->create();
    $technician->assignRole(Role::findOrCreate('maintenance-technician', 'web'));

    $this->actingAs($superAdmin);

    Livewire::test(ListUsers::class)
        ->callTableAction('impersonate', $technician)
        ->assertRedirect(Filament::getPanel('maintenance')->getUrl());

    $this->assertAuthenticatedAs($technician);
    expect(Impersonation::isImpersonating())->toBeTrue();
});

it('selects a preferred panel the user can access', function (string $role, string $panelId): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));

    expect($user->getPreferredPanelId())->toBe($panelId)
        ->and($user->canAccessPanelById($panelId))->toBeTrue();
})->with([
    'maintenance manager' => ['maintenance-manager', 'maintenance'],
    'sales' => ['sales', 'sales'],
    'employee' => ['employee', 'team'],
    'admin' => ['admin', 'admin'],
]);

it('keeps the admin panel as the default for users with broad and specialized access', function (): void {
    $user = User::factory()->create();
    $user->assignRole([
        Role::findOrCreate('admin', 'web'),
        Role::findOrCreate('maintenance-manager', 'web'),
    ]);

    expect($user->getPreferredPanelId())->toBe('admin');
});

it('restores the super admin and audits entry and exit', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin', 'web'));

    $employee = User::factory()->create();
    $employee->assignRole(Role::findOrCreate('employee', 'web'));

    $this->actingAs($superAdmin);

    $startedCount = Activity::query()->where('event', 'impersonation_started')->count();
    $endedCount = Activity::query()->where('event', 'impersonation_ended')->count();

    expect(Impersonation::enter($superAdmin, $employee, 'web'))->toBeTrue()
        ->and(Impersonation::isImpersonating())->toBeTrue();

    $this->assertAuthenticatedAs($employee);

    expect(Activity::query()->where('event', 'impersonation_started')->count())->toBe($startedCount + 1);

    $started = Activity::query()->where('event', 'impersonation_started')->latest('id')->firstOrFail();

    expect($started->causer->is($superAdmin))->toBeTrue()
        ->and($started->subject->is($employee))->toBeTrue()
        ->and($started->properties->get('impersonator_id'))->toBe($superAdmin->getKey())
        ->and($started->properties->get('impersonated_id'))->toBe($employee->getKey());

    expect(Impersonation::leave())->toBeTrue()
        ->and(Impersonation::isImpersonating())->toBeFalse();

    $this->assertAuthenticatedAs($superAdmin);

    expect(Activity::query()->where('event', 'impersonation_ended')->count())->toBe($endedCount + 1);

    $ended = Activity::query()->where('event', 'impersonation_ended')->latest('id')->firstOrFail();

    expect($ended->causer->is($superAdmin))->toBeTrue()
        ->and($ended->subject->is($employee))->toBeTrue();
});
