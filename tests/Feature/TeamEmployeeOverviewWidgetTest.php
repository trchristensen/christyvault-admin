<?php

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
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

it('uses scoped permissions rather than role names for the team time-off overview', function (): void {
    $viewPlant = Permission::findOrCreate(User::VIEW_PLANT_TIME_OFF_REQUESTS_PERMISSION, 'web');
    $viewAll = Permission::findOrCreate(User::VIEW_ALL_TIME_OFF_REQUESTS_PERMISSION, 'web');
    $managePlant = Permission::findOrCreate(User::MANAGE_PLANT_TIME_OFF_REQUESTS_PERMISSION, 'web');
    [$ordinaryEmployee] = overviewUser('Ordinary Employee');
    [$plantViewer] = overviewUser('Plant Viewer', 'driver');
    [$plantApprover] = overviewUser('Plant Approver', 'foreman');
    [$companyViewer] = overviewUser('Company Viewer', 'manager', withEmployee: false);
    [$unlinkedPlantViewer] = overviewUser('Unlinked Plant Viewer', 'manager', withEmployee: false);

    $plantViewer->givePermissionTo($viewPlant);
    $plantApprover->givePermissionTo($managePlant);
    $companyViewer->givePermissionTo($viewAll);
    $unlinkedPlantViewer->givePermissionTo($viewPlant);

    expect($ordinaryEmployee->canViewTeamTimeOffOverview())->toBeFalse()
        ->and($plantViewer->canViewTeamTimeOffOverview())->toBeTrue()
        ->and($plantApprover->canViewTeamTimeOffOverview())->toBeTrue()
        ->and($companyViewer->canViewTeamTimeOffOverview())->toBeTrue()
        ->and($unlinkedPlantViewer->canViewTeamTimeOffOverview())->toBeFalse();
});

it('shows leadership pending requests and approved absences for all active employees', function (string $role): void {
    $usesCompanyScope = in_array($role, ['admin', 'super-admin'], true);
    [$leader] = overviewUser('Team Leader', $role, withEmployee: ! $usesCompanyScope);
    $leader->givePermissionTo(Permission::findOrCreate(
        $usesCompanyScope
            ? User::VIEW_ALL_TIME_OFF_REQUESTS_PERMISSION
            : User::VIEW_PLANT_TIME_OFF_REQUESTS_PERMISSION,
        'web',
    ));
    [, $pendingEmployee] = overviewUser('Alex Pending');
    [, $approvedEmployee] = overviewUser('Bailey Approved');
    [, $inactiveEmployee] = overviewUser('Inactive Employee', active: false);

    insertOverviewLeave($pendingEmployee, 'pending', 'vacation', reason: 'Pending private details');
    insertOverviewLeave($approvedEmployee, 'approved', 'sick', startsInDays: 10, reason: 'Approved private details');
    insertOverviewLeave($inactiveEmployee, 'approved', 'unpaid', startsInDays: 12);
    insertOverviewLeave($approvedEmployee, 'rejected', 'other', startsInDays: 14);

    $response = $this->actingAs($leader)->get('/team');

    $response
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
        ->assertDontSee('No employee profile is linked');

    if ($usesCompanyScope) {
        $response->assertDontSee('Request time off');
    } else {
        $response->assertSee('Request time off');
    }
})->with(['super-admin', 'admin', 'manager', 'foreman']);

it('keeps plant-scoped time off inside the viewers plant', function (): void {
    [$foreman] = overviewUser('Tulare Foreman', 'foreman');
    $foreman->employee->update(['christy_location' => 'tulare']);
    $foreman->givePermissionTo(Permission::findOrCreate(User::VIEW_PLANT_TIME_OFF_REQUESTS_PERMISSION, 'web'));
    [, $tulareEmployee] = overviewUser('Tulare Employee');
    $tulareEmployee->update(['christy_location' => 'tulare']);
    [, $colmaEmployee] = overviewUser('Colma Employee');

    insertOverviewLeave($tulareEmployee, 'pending', 'vacation');
    insertOverviewLeave($colmaEmployee, 'pending', 'sick');

    $this->actingAs($foreman)
        ->get('/team')
        ->assertOk()
        ->assertSee('Team Time Off')
        ->assertSee('Tulare Employee')
        ->assertDontSee('Colma Employee');
});

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
