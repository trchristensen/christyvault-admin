<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use SpykApp\PasswordlessLogin\Facades\PasswordlessLogin;

uses(DatabaseTransactions::class);

it('redirects an authenticated user away from an inaccessible panel', function (string $role, string $wrongPanel, string $destination): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));

    $this->actingAs($user)
        ->get($wrongPanel)
        ->assertRedirect($destination);
})->with([
    'employee at admin' => ['employee', '/', '/team'],
    'sales at maintenance' => ['sales', '/maintenance', '/sales'],
    'technician at team' => ['maintenance-technician', '/team', '/maintenance'],
]);

it('resolves the shared post-login route to the preferred panel', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('employee', 'web'));

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/team');
});

it('lands a magic-link login in the users preferred panel', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('sales', 'web'));

    $magicLink = PasswordlessLogin::forUser($user)
        ->withoutNotification()
        ->generate(request())['url'];

    $this->get($magicLink.'?confirmed=1')
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);

    $this->get('/dashboard')
        ->assertRedirect('/sales');
});

it('does not redirect an account without access to any panel', function (): void {
    $user = User::factory()->create();

    expect($user->getPreferredPanelId())->toBeNull();

    $this->actingAs($user)
        ->get('/')
        ->assertForbidden();
});
