<?php

use App\Filament\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function signInForEmployeeOnboarding(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    test()->actingAs($user);
    Filament::setCurrentPanel('admin');

    return $user;
}

it('creates an employee before contact details or a user account are available', function (): void {
    signInForEmployeeOnboarding();
    $position = Position::query()->firstOrCreate(
        ['name' => 'production'],
        ['display_name' => 'Production'],
    );
    $name = 'Optional Onboarding '.str()->uuid();

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'user_id' => null,
            'name' => $name,
            'email' => null,
            'phone' => null,
            'address' => null,
            'positions' => [$position->getKey()],
            'christy_location' => 'colma',
            'hire_date' => null,
            'birth_date' => null,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $employee = Employee::query()->where('name', $name)->firstOrFail();

    expect($employee->user_id)->toBeNull()
        ->and($employee->email)->toBeNull()
        ->and($employee->hire_date)->toBeNull()
        ->and($employee->birth_date)->toBeNull()
        ->and($employee->positions()->whereKey($position->getKey())->exists())->toBeTrue();
});

it('uses the driver position without requiring an empty driver profile', function (): void {
    signInForEmployeeOnboarding();
    $driverPosition = Position::query()->firstOrCreate(
        ['name' => 'driver'],
        ['display_name' => 'Driver'],
    );
    $name = 'Position Driver '.str()->uuid();

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'name' => $name,
            'positions' => [$driverPosition->getKey()],
            'christy_location' => 'tulare',
            'is_active' => true,
            'driver' => [
                'license_number' => null,
                'license_expiration' => null,
                'notes' => null,
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $employee = Employee::query()->where('name', $name)->firstOrFail();

    expect($employee->isDriver())->toBeTrue()
        ->and($employee->driver()->exists())->toBeFalse();
});

it('retains optional license details when they are provided for a driver', function (): void {
    signInForEmployeeOnboarding();
    $driverPosition = Position::query()->firstOrCreate(
        ['name' => 'driver'],
        ['display_name' => 'Driver'],
    );
    $name = 'Licensed Driver '.str()->uuid();

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'name' => $name,
            'positions' => [$driverPosition->getKey()],
            'christy_location' => 'colma',
            'is_active' => true,
            'driver' => [
                'license_number' => 'TEST-LICENSE-123',
                'license_expiration' => '2030-01-15',
                'notes' => 'Optional test details',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $employee = Employee::query()->where('name', $name)->firstOrFail();

    expect($employee->driver)->not->toBeNull()
        ->and($employee->driver->license_number)->toBe('TEST-LICENSE-123')
        ->and($employee->driver->license_expiration->toDateString())->toBe('2030-01-15');
});
