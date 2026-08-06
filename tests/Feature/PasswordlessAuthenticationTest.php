<?php

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

it('renders magic-link-only login forms for every panel', function (): void {
    foreach (['/login', '/team/login', '/maintenance/login', '/operations/login', '/sales/login'] as $path) {
        $this->get($path)
            ->assertOk()
            ->assertSee('Send Magic Link')
            ->assertDontSee('type="password"', false);
    }
});

it('creates a production-style user without a password', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');
    $email = 'passwordless-'.str()->uuid().'@example.test';

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Passwordless User',
            'email' => $email,
            'password' => null,
            'roles' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', $email)->firstOrFail()->password)->toBeNull();
});

it('does not issue API tokens through the retired password endpoint', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/tokens/create', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'legacy-client',
    ])
        ->assertGone()
        ->assertJson([
            'message' => 'Password token login is no longer available.',
        ])
        ->assertJsonMissing(['token']);

    expect($user->tokens()->count())->toBe(0);
});

it('keeps personal profile forms without a change-password section', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    $this->actingAs($admin);

    foreach (['/my-profile', '/team/my-profile', '/maintenance/my-profile', '/operations/my-profile', '/sales/my-profile'] as $path) {
        $this->get($path)
            ->assertOk()
            ->assertDontSee('Update Password')
            ->assertDontSee('type="password"', false);
    }
});
