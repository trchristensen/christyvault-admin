<?php

use App\Filament\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Filament\Resources\EmployeeResource\Pages\EditEmployee;
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

it('renders the edit form when the employee already has a user account', function (): void {
    signInForEmployeeOnboarding();
    $linkedUser = User::factory()->create();
    $employee = Employee::query()->create([
        'user_id' => $linkedUser->getKey(),
        'name' => 'Linked Employee '.str()->uuid(),
        'christy_location' => 'colma',
        'is_active' => true,
    ]);

    Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
        ->assertSuccessful()
        ->assertFormSet([
            'user_id' => $linkedUser->getKey(),
        ]);
});

it('creates an employee before contact details or a user account are available', function (): void {
    signInForEmployeeOnboarding();
    $position = Position::query()->firstOrCreate(
        ['name' => 'production'],
        ['display_name' => 'Production'],
    );
    $name = 'Optional Onboarding '.str()->uuid();
    [$firstName, $lastName] = explode(' ', $name, 2);

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'user_id' => null,
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
            'suffix' => null,
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
    [$firstName, $lastName] = explode(' ', $name, 2);

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'first_name' => $firstName,
            'last_name' => $lastName,
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
    [$firstName, $lastName] = explode(' ', $name, 2);

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'first_name' => $firstName,
            'last_name' => $lastName,
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

it('keeps structured and legacy employee names synchronized', function (): void {
    $employee = Employee::query()->create([
        'first_name' => 'Samuel',
        'middle_name' => 'Robert',
        'last_name' => 'Avelar',
        'suffix' => 'Jr.',
        'christy_location' => 'colma',
        'is_active' => true,
    ]);

    expect($employee->name)->toBe('Samuel Robert Avelar Jr.');

    $employee->update(['name' => 'Frank Xavier Salazar Sr.']);

    expect($employee->refresh()->first_name)->toBe('Frank')
        ->and($employee->middle_name)->toBe('Xavier')
        ->and($employee->last_name)->toBe('Salazar')
        ->and($employee->suffix)->toBe('Sr.')
        ->and($employee->name)->toBe('Frank Xavier Salazar Sr.');
});
