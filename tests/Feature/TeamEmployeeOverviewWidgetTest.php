<?php

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function overviewUser(string $name, string $role = 'employee', bool $withEmployee = true, bool $active = true): array
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole(Role::findOrCreate($role, 'web'));

    $employee = $withEmployee
        ? Employee::create([
            'user_id' => $user->getKey(),
            'name' => $name,
            'email' => $user->email,
            'is_active' => $active,
            'christy_location' => 'colma',
        ])
        : null;

    return [$user, $employee];
}

function insertOverviewLeave(
    Employee $employee,
    string $status,
    string $type,
    int $startsInDays = 7,
    string $reason = 'Private request note',
): LeaveRequest {
    $id = DB::table('leave_requests')->insertGetId([
        'employee_id' => $employee->getKey(),
        'type' => $type,
        'start_date' => today()->addDays($startsInDays),
        'end_date' => today()->addDays($startsInDays + 1),
        'reason' => $reason,
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return LeaveRequest::findOrFail($id);
}

it('gives only the designated leadership roles the team time-off overview', function (): void {
    foreach (['super-admin', 'admin', 'manager', 'foreman'] as $role) {
        [$user] = overviewUser("{$role} User", $role, withEmployee: false);

        expect($user->canViewTeamTimeOffOverview())->toBeTrue();
    }

    foreach (['employee', 'driver', 'tulare-driver'] as $role) {
        [$user] = overviewUser("{$role} User", $role);

        expect($user->canViewTeamTimeOffOverview())->toBeFalse();
    }
});

it('shows leadership pending requests and approved absences for all active employees', function (string $role): void {
    [$leader] = overviewUser('Team Leader', $role, withEmployee: false);
    [, $pendingEmployee] = overviewUser('Alex Pending');
    [, $approvedEmployee] = overviewUser('Bailey Approved');
    [, $inactiveEmployee] = overviewUser('Inactive Employee', active: false);

    insertOverviewLeave($pendingEmployee, 'pending', 'vacation', reason: 'Pending private details');
    insertOverviewLeave($approvedEmployee, 'approved', 'sick', startsInDays: 10, reason: 'Approved private details');
    insertOverviewLeave($inactiveEmployee, 'approved', 'unpaid', startsInDays: 12);
    insertOverviewLeave($approvedEmployee, 'rejected', 'other', startsInDays: 14);

    $this->actingAs($leader)
        ->get('/team')
        ->assertOk()
        ->assertSee('Team Time Off')
        ->assertSee('Pending requests')
        ->assertSee('Approved time off')
        ->assertSee('Alex Pending')
        ->assertSee('Bailey Approved')
        ->assertSee('Vacation')
        ->assertSee('Sick')
        ->assertDontSee('Inactive Employee')
        ->assertDontSee('Pending private details')
        ->assertDontSee('Approved private details')
        ->assertDontSee('Request time off')
        ->assertDontSee('No employee profile is linked');
})->with(['super-admin', 'admin', 'manager', 'foreman']);

it('keeps ordinary employees on their personal time-off view', function (): void {
    [$user, $employee] = overviewUser('Taylor Employee');
    [, $otherEmployee] = overviewUser('Other Employee');

    insertOverviewLeave($employee, 'pending', 'vacation');
    insertOverviewLeave($otherEmployee, 'approved', 'unpaid');

    $this->actingAs($user)
        ->get('/team')
        ->assertOk()
        ->assertSee('My Time Off')
        ->assertSee('Vacation')
        ->assertSee('Request time off')
        ->assertDontSee('Team Time Off')
        ->assertDontSee('Other Employee')
        ->assertDontSee('Unpaid');
});
