<?php

use App\Filament\Resources\LeaveRequestResource as AdminLeaveRequestResource;
use App\Filament\Team\Resources\LeaveRequestResource;
use App\Filament\Team\Resources\LeaveRequestResource\Pages\CreateLeaveRequest;
use App\Filament\Team\Resources\LeaveRequestResource\Pages\EditLeaveRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestSubmitted;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function teamUserWithEmployee(string $name, string $role = 'employee', string $location = 'colma'): array
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole(Role::findOrCreate($role, 'web'));

    $employee = Employee::create([
        'user_id' => $user->getKey(),
        'name' => $name,
        'email' => $user->email,
        'is_active' => true,
        'christy_location' => $location,
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

function formatLeaveDateRange(CarbonInterface $startDate, CarbonInterface $endDate): string
{
    return $startDate->format(LeaveRequest::DATE_RANGE_FORMAT)
        .LeaveRequest::DATE_RANGE_SEPARATOR
        .$endDate->format(LeaveRequest::DATE_RANGE_FORMAT);
}

function formatLeaveDateTimeRange(CarbonInterface $startDate, CarbonInterface $endDate): string
{
    return $startDate->format(LeaveRequest::DATE_TIME_RANGE_FORMAT)
        .LeaveRequest::DATE_RANGE_SEPARATOR
        .$endDate->format(LeaveRequest::DATE_TIME_RANGE_FORMAT);
}

it('configures the team time range picker with one time segment', function (): void {
    [$user] = teamUserWithEmployee('Time Picker Format Employee');
    $this->actingAs($user);
    Filament::setCurrentPanel('team');

    $picker = Livewire::test(CreateLeaveRequest::class)
        ->instance()
        ->form
        ->getComponent('date_time_range', withHidden: true);

    expect($picker)->toBeInstanceOf(DateRangePicker::class)
        ->and($picker->getEnforceFormat())->toBeTrue()
        ->and($picker->getFormat())->toBe(LeaveRequest::DATE_TIME_RANGE_FORMAT)
        ->and($picker->getDisplayFormat())->toBe('MMM D, YYYY h:mm A')
        ->and($picker->getExtraAlpineAttributes()['x-init'])->toContain('endTime.minute = 0');
});

it('shows time off requests in the team sidebar for linked employees', function (): void {
    [$user] = teamUserWithEmployee('Taylor Employee');

    $response = $this->actingAs($user)->get('/team');

    $response
        ->assertOk()
        ->assertSee('Welcome, Taylor')
        ->assertSee('Time Off Requests')
        ->assertSee('Request time off')
        ->assertSee('href="'.LeaveRequestResource::getUrl('create', panel: 'team').'"', false);

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

it('creates a time off request from the combined date range field', function (): void {
    [$user, $employee] = teamUserWithEmployee('Range Picker Employee');
    $startDate = today()->next(CarbonInterface::MONDAY);
    $endDate = $startDate->copy()->addDays(4);

    $this->actingAs($user);
    Filament::setCurrentPanel('team');

    Livewire::test(CreateLeaveRequest::class)
        ->fillForm([
            'type' => 'vacation',
            'date_range' => formatLeaveDateRange($startDate, $endDate),
            'reason' => 'Family plans',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = LeaveRequest::query()
        ->where('employee_id', $employee->getKey())
        ->latest('id')
        ->firstOrFail();

    expect($request->start_date->toDateString())->toBe($startDate->toDateString())
        ->and($request->end_date->toDateString())->toBe($endDate->toDateString())
        ->and($request->date_range)->toBe(formatLeaveDateRange($startDate, $endDate));
});

it('creates a specific-hours request from the timed range field', function (): void {
    [$user, $employee] = teamUserWithEmployee('Specific Hours Employee');
    $startDate = today()->next(CarbonInterface::MONDAY)->setTime(9, 30);
    $endDate = $startDate->copy()->setTime(13, 0);
    $range = formatLeaveDateTimeRange($startDate, $endDate);

    $this->actingAs($user);
    Filament::setCurrentPanel('team');

    Livewire::test(CreateLeaveRequest::class)
        ->fillForm([
            'type' => 'vacation',
            'duration' => 'specific_hours',
            'date_time_range' => $range,
            'reason' => 'Appointment',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = LeaveRequest::query()
        ->where('employee_id', $employee->getKey())
        ->latest('id')
        ->firstOrFail();

    expect($request->start_date->format('Y-m-d H:i'))->toBe($startDate->format('Y-m-d H:i'))
        ->and($request->end_date->format('Y-m-d H:i'))->toBe($endDate->format('Y-m-d H:i'))
        ->and($request->hasSpecificTimes())->toBeTrue()
        ->and($request->date_time_range)->toBe($range)
        ->and($request->dateSummary())->toContain('9:30 AM', '1:00 PM');
});

it('requires a specific-hours request to end after it starts', function (): void {
    [$user] = teamUserWithEmployee('Invalid Hours Employee');
    $startDate = today()->next(CarbonInterface::MONDAY)->setTime(14, 0);
    $endDate = $startDate->copy()->setTime(9, 0);

    $this->actingAs($user);
    Filament::setCurrentPanel('team');

    Livewire::test(CreateLeaveRequest::class)
        ->fillForm([
            'type' => 'vacation',
            'duration' => 'specific_hours',
            'date_time_range' => formatLeaveDateTimeRange($startDate, $endDate),
        ])
        ->call('create')
        ->assertHasFormErrors(['date_time_range']);
});

it('rejects weekend endpoints even when picker restrictions are bypassed', function (): void {
    [$user] = teamUserWithEmployee('Weekend Validation Employee');
    $startDate = today()->next(CarbonInterface::SATURDAY);
    $endDate = $startDate->copy()->addDays(2);

    $this->actingAs($user);
    Filament::setCurrentPanel('team');

    Livewire::test(CreateLeaveRequest::class)
        ->fillForm([
            'type' => 'vacation',
            'date_range' => formatLeaveDateRange($startDate, $endDate),
        ])
        ->call('create')
        ->assertHasFormErrors(['date_range']);
});

it('hydrates and updates the combined date range on pending requests', function (): void {
    [$user, $employee] = teamUserWithEmployee('Range Editor Employee');
    $request = insertLeaveRequest($employee->getKey());
    $startDate = today()->next(CarbonInterface::MONDAY);
    $endDate = $startDate->copy()->addDays(2);
    $range = formatLeaveDateRange($startDate, $endDate);

    $this->actingAs($user);
    Filament::setCurrentPanel('team');

    Livewire::test(EditLeaveRequest::class, ['record' => $request->getRouteKey()])
        ->assertFormSet([
            'duration' => 'full_day',
            'date_range' => $request->date_range,
        ])
        ->fillForm([
            'date_range' => $range,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $request->refresh();

    expect($request->start_date->toDateString())->toBe($startDate->toDateString())
        ->and($request->end_date->toDateString())->toBe($endDate->toDateString())
        ->and($request->date_range)->toBe($range);
});

it('hydrates specific hours when editing a pending request', function (): void {
    [$user, $employee] = teamUserWithEmployee('Specific Hours Editor');
    $request = insertLeaveRequest($employee->getKey());
    $request->update([
        'start_date' => today()->next(CarbonInterface::TUESDAY)->setTime(8, 0),
        'end_date' => today()->next(CarbonInterface::TUESDAY)->setTime(12, 30),
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel('team');

    Livewire::test(EditLeaveRequest::class, ['record' => $request->getRouteKey()])
        ->assertFormSet([
            'duration' => 'specific_hours',
            'date_time_range' => $request->date_time_range,
        ]);
});

it('notifies only approvers whose permission scope includes the request', function (): void {
    $viewPlant = Permission::findOrCreate(User::VIEW_PLANT_TIME_OFF_REQUESTS_PERMISSION, 'web');
    $managePlant = Permission::findOrCreate(User::MANAGE_PLANT_TIME_OFF_REQUESTS_PERMISSION, 'web');
    $manageAll = Permission::findOrCreate(User::MANAGE_ALL_TIME_OFF_REQUESTS_PERMISSION, 'web');
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    $admin->givePermissionTo($manageAll);
    [$plantApprover] = teamUserWithEmployee('Colma Approver', 'foreman', 'colma');
    $plantApprover->givePermissionTo($managePlant);
    [$plantViewer] = teamUserWithEmployee('Colma Viewer', 'manager', 'colma');
    $plantViewer->givePermissionTo($viewPlant);
    [$otherPlantApprover] = teamUserWithEmployee('Tulare Approver', 'foreman', 'tulare');
    $otherPlantApprover->givePermissionTo($managePlant);
    [$employeeUser, $employee] = teamUserWithEmployee('Notification Employee');
    $startDate = today()->next(CarbonInterface::MONDAY);
    $endDate = $startDate->copy()->addDays(2);

    $this->actingAs($employeeUser);
    Filament::setCurrentPanel('team');

    Livewire::test(CreateLeaveRequest::class)
        ->fillForm([
            'type' => 'vacation',
            'date_range' => formatLeaveDateRange($startDate, $endDate),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = LeaveRequest::query()
        ->where('employee_id', $employee->getKey())
        ->latest('id')
        ->firstOrFail();
    $notification = $admin->notifications()->firstOrFail();
    $plantNotification = $plantApprover->notifications()->firstOrFail();

    expect($notification->type)->toBe(LeaveRequestSubmitted::class)
        ->and($notification->data['panel'])->toBe('admin')
        ->and($notification->data['title'])->toBe('New time-off request from Notification Employee')
        ->and($notification->data['body'])->toContain('Vacation')
        ->and($notification->data['leave_request_id'])->toBe($request->getKey())
        ->and($notification->data['actions'][0]['url'])->toContain("/leave-requests/{$request->getKey()}/edit")
        ->and($plantNotification->data['panel'])->toBe('team')
        ->and($plantNotification->data['actions'][0]['url'])->toContain("/team/time-off-requests/{$request->getKey()}/edit")
        ->and($plantViewer->notifications()->count())->toBe(0)
        ->and($otherPlantApprover->notifications()->count())->toBe(0);
});

it('scopes viewing and management permissions by plant', function (): void {
    $viewPlant = Permission::findOrCreate(User::VIEW_PLANT_TIME_OFF_REQUESTS_PERMISSION, 'web');
    $managePlant = Permission::findOrCreate(User::MANAGE_PLANT_TIME_OFF_REQUESTS_PERMISSION, 'web');
    [$foreman] = teamUserWithEmployee('Tulare Permission Foreman', 'foreman', 'tulare');
    [, $tulareEmployee] = teamUserWithEmployee('Tulare Permission Employee', location: 'tulare');
    [, $colmaEmployee] = teamUserWithEmployee('Colma Permission Employee');
    $tulareRequest = insertLeaveRequest($tulareEmployee->getKey());
    $colmaRequest = insertLeaveRequest($colmaEmployee->getKey());
    $foreman->givePermissionTo($viewPlant);

    $this->actingAs($foreman);
    Filament::setCurrentPanel('team');

    expect(LeaveRequestResource::getEloquentQuery()->pluck('id')->all())->toBe([$tulareRequest->getKey()])
        ->and(LeaveRequestResource::canEdit($tulareRequest))->toBeFalse()
        ->and(LeaveRequestResource::canEdit($colmaRequest))->toBeFalse();

    $foreman->givePermissionTo($managePlant);

    expect(LeaveRequestResource::canEdit($tulareRequest))->toBeTrue()
        ->and(LeaveRequestResource::canEdit($colmaRequest))->toBeFalse();

    Livewire::test(EditLeaveRequest::class, ['record' => $tulareRequest->getRouteKey()])
        ->fillForm([
            'status' => 'approved',
            'review_notes' => 'Coverage confirmed.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $tulareRequest->refresh();

    expect($tulareRequest->status)->toBe('approved')
        ->and($tulareRequest->reviewed_by)->toBe($foreman->getKey())
        ->and($tulareRequest->review_notes)->toBe('Coverage confirmed.');
});

it('shows the number of pending requests in the admin sidebar', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    $admin->givePermissionTo(Permission::findOrCreate(User::MANAGE_ALL_TIME_OFF_REQUESTS_PERMISSION, 'web'));
    [, $employee] = teamUserWithEmployee('Badge Employee');

    insertLeaveRequest($employee->getKey());
    insertLeaveRequest($employee->getKey());
    insertLeaveRequest($employee->getKey(), 'approved');

    $this->actingAs($admin);

    expect(AdminLeaveRequestResource::getNavigationBadge())->toBe('2')
        ->and(AdminLeaveRequestResource::getNavigationBadgeColor())->toBe('warning')
        ->and(AdminLeaveRequestResource::getNavigationBadgeTooltip())->toBe('Pending time-off requests');
});
