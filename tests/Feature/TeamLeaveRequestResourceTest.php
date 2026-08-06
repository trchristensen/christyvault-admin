<?php

use App\Filament\Team\Resources\LeaveRequestResource;
use App\Filament\Team\Resources\LeaveRequestResource\Pages\CreateLeaveRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function teamUserWithEmployee(string $name): array
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole(Role::findOrCreate('employee', 'web'));

    $employee = Employee::create([
        'user_id' => $user->getKey(),
        'name' => $name,
        'email' => $user->email,
        'is_active' => true,
        'christy_location' => 'colma',
        'hire_date' => '2020-01-01',
        'birth_date' => '1990-01-01',
    ]);

    return [$user, $employee];
}

function insertLeaveRequest(int $employeeId, string $status = 'pending'): LeaveRequest
{
    $id = DB::table('leave_requests')->insertGetId([
        'employee_id' => $employeeId,
        'type' => 'vacation',
        'start_date' => today()->addWeek(),
        'end_date' => today()->addWeek()->addDay(),
        'reason' => 'Family plans',
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return LeaveRequest::findOrFail($id);
}

it('shows time off requests in the team sidebar for linked employees', function (): void {
    [$user] = teamUserWithEmployee('Taylor Employee');

    $response = $this->actingAs($user)->get('/team');

    $response
        ->assertOk()
        ->assertSee('Welcome, Taylor')
        ->assertSee('Time Off Requests');

    $this->get('/team/time-off-requests')->assertOk();
    $this->get('/team/time-off-requests/create')->assertOk();

    expect(LeaveRequestResource::canViewAny())->toBeTrue()
        ->and(LeaveRequestResource::canCreate())->toBeTrue();
});

it('scopes the team resource to the signed in employee', function (): void {
    [$user, $employee] = teamUserWithEmployee('Taylor Employee');
    [, $otherEmployee] = teamUserWithEmployee('Other Employee');
    $ownRequest = insertLeaveRequest($employee->getKey());
    $otherRequest = insertLeaveRequest($otherEmployee->getKey());

    $this->actingAs($user);

    expect(LeaveRequestResource::getEloquentQuery()->pluck('id')->all())
        ->toBe([$ownRequest->getKey()]);

    $this->get("/team/time-off-requests/{$otherRequest->getKey()}/edit")->assertNotFound();
});

it('allows employees to change only their own pending requests', function (): void {
    [$user, $employee] = teamUserWithEmployee('Taylor Employee');
    [, $otherEmployee] = teamUserWithEmployee('Other Employee');
    $pending = insertLeaveRequest($employee->getKey());
    $approved = insertLeaveRequest($employee->getKey(), 'approved');
    $other = insertLeaveRequest($otherEmployee->getKey());

    $this->actingAs($user);

    expect(LeaveRequestResource::canEdit($pending))->toBeTrue()
        ->and(LeaveRequestResource::canDelete($pending))->toBeTrue()
        ->and(LeaveRequestResource::canEdit($approved))->toBeFalse()
        ->and(LeaveRequestResource::canDelete($approved))->toBeFalse()
        ->and(LeaveRequestResource::canEdit($other))->toBeFalse()
        ->and(LeaveRequestResource::canDelete($other))->toBeFalse()
        ->and(LeaveRequestResource::canDeleteAny())->toBeFalse();
});

it('forces new requests to the signed in employee with pending status', function (): void {
    [$user, $employee] = teamUserWithEmployee('Taylor Employee');
    [, $otherEmployee] = teamUserWithEmployee('Other Employee');
    $this->actingAs($user);

    $page = new class extends CreateLeaveRequest
    {
        public function prepareForTest(array $data): array
        {
            return $this->mutateFormDataBeforeCreate($data);
        }
    };

    $data = $page->prepareForTest([
        'employee_id' => $otherEmployee->getKey(),
        'type' => 'vacation',
        'start_date' => today()->addWeek()->toDateString(),
        'end_date' => today()->addWeek()->addDay()->toDateString(),
        'status' => 'approved',
        'reviewed_by' => $otherEmployee->getKey(),
    ]);

    expect($data['employee_id'])->toBe($employee->getKey())
        ->and($data['status'])->toBe('pending')
        ->and($data['reviewed_by'])->toBeNull()
        ->and($data['review_notes'])->toBeNull();
});
