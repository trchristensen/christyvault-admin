<?php

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Employee;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function signInForUserSettings(): void
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));

    test()->actingAs($admin);
    Filament::setCurrentPanel('admin');
}

it('hides employee settings for a user without an employee profile', function (): void {
    signInForUserSettings();
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Access')
        ->assertDontSee('Employee Settings')
        ->assertDontSee('Visible Delivery Types');
});

it('shows employee settings for a linked employee account', function (): void {
    signInForUserSettings();
    $user = User::factory()->create();
    Employee::query()->create([
        'user_id' => $user->getKey(),
        'name' => 'Linked Employee '.str()->uuid(),
        'christy_location' => 'colma',
        'is_active' => true,
    ]);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Employee Settings')
        ->assertSee('Associated Employee')
        ->assertSee('Visible Delivery Types')
        ->assertSee('Days Ahead');
});
