<?php

use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\SharedProfileUpdater;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function teamProfileUser(): array
{
    $user = User::factory()->create([
        'name' => 'Original Display Name',
        'email' => 'profile-'.str()->uuid().'@example.test',
    ]);
    $user->assignRole(Role::findOrCreate('employee', 'web'));

    $employee = Employee::create([
        'user_id' => $user->getKey(),
        'name' => 'Official Employee Name',
        'email' => null,
        'phone' => null,
        'address' => 'Old address',
        'is_active' => true,
        'christy_location' => 'colma',
        'hire_date' => '2020-02-03',
        'birth_date' => null,
    ]);
    $position = Position::query()->firstOrCreate(
        ['name' => 'production'],
        ['display_name' => 'Production'],
    );
    $employee->positions()->sync([$position->getKey()]);

    test()->actingAs($user);
    Filament::setCurrentPanel('team');

    return [$user, $employee, $position];
}

it('allows a display name without changing login or employee identity', function (): void {
    [$user, $employee] = teamProfileUser();
    $loginEmail = $user->email;

    Livewire::test('profile-account-information')
        ->fillForm([
            'name' => 'Preferred Display Name',
            'email' => 'changed-login@example.test',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect($user->fresh()->name)->toBe('Preferred Display Name')
        ->and($user->fresh()->email)->toBe($loginEmail)
        ->and($employee->fresh()->name)->toBe('Official Employee Name');
});

it('allows contact updates while keeping employment fields office managed', function (): void {
    [, $employee, $position] = teamProfileUser();

    Livewire::test('profile-employee-information')
        ->assertSee('Contact information only. It does not change how you sign in.')
        ->fillForm([
            'phone' => '+14155550123',
            'address' => '123 Updated Avenue',
            'official_name' => 'Attempted Name Change',
            'plant' => 'Tulare',
            'hire_date' => 'Yesterday',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    app(SharedProfileUpdater::class)->updateEmployeeContact(
        auth()->user(),
        ['positions' => 'Manager'],
    );

    $employee->refresh();

    expect($employee->phone)->toBe('+14155550123')
        ->and($employee->address)->toBe('123 Updated Avenue')
        ->and($employee->name)->toBe('Official Employee Name')
        ->and($employee->christy_location)->toBe('colma')
        ->and($employee->hire_date->toDateString())->toBe('2020-02-03')
        ->and($employee->positions()->pluck('positions.id')->all())->toBe([$position->getKey()]);
});

it('shows the shared profile in team without exposing schedule visibility controls', function (): void {
    teamProfileUser();

    $this->get('/team/my-profile')
        ->assertOk()
        ->assertSee('Display name')
        ->assertSee('Employment and contact')
        ->assertSee('Contact information only. It does not change how you sign in.')
        ->assertDontSee('Position(s)')
        ->assertDontSee('Production')
        ->assertDontSee('Visible Delivery Types')
        ->assertDontSee('Days Ahead')
        ->assertDontSee('Update Password');
});
