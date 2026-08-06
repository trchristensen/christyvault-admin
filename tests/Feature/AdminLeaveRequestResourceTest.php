<?php

use App\Filament\Resources\LeaveRequestResource\Pages\CreateLeaveRequest;
use App\Filament\Resources\LeaveRequestResource\Pages\EditLeaveRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function signInForAdminLeaveRequests(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    test()->actingAs($user);
    Filament::setCurrentPanel('admin');

    return $user;
}

function adminLeaveEmployee(): Employee
{
    return Employee::create([
        'name' => 'Admin Leave Employee '.str()->uuid(),
        'is_active' => true,
        'christy_location' => 'colma',
    ]);
}

function adminDateRange(CarbonInterface $startDate, CarbonInterface $endDate, bool $includesTime = false): string
{
    $format = $includesTime
        ? LeaveRequest::DATE_TIME_RANGE_FORMAT
        : LeaveRequest::DATE_RANGE_FORMAT;

    return $startDate->format($format)
        .LeaveRequest::DATE_RANGE_SEPARATOR
        .$endDate->format($format);
}

it('uses the combined date range to create full-day admin requests', function (): void {
    signInForAdminLeaveRequests();
    $employee = adminLeaveEmployee();
    $startDate = today()->previous(CarbonInterface::MONDAY);
    $endDate = $startDate->copy()->addDays(2);

    Livewire::test(CreateLeaveRequest::class)
        ->fillForm([
            'employee_id' => $employee->getKey(),
            'type' => 'vacation',
            'duration' => 'full_day',
            'date_range' => adminDateRange($startDate, $endDate),
            'status' => 'pending',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = LeaveRequest::query()
        ->where('employee_id', $employee->getKey())
        ->latest('id')
        ->firstOrFail();

    expect($request->start_date->toDateString())->toBe($startDate->toDateString())
        ->and($request->end_date->toDateString())->toBe($endDate->toDateString())
        ->and($request->hasSpecificTimes())->toBeFalse();
});

it('hydrates and updates specific hours in the admin range picker', function (): void {
    signInForAdminLeaveRequests();
    $employee = adminLeaveEmployee();
    $startDate = today()->next(CarbonInterface::TUESDAY)->setTime(8, 30);
    $endDate = $startDate->copy()->setTime(12, 0);
    $request = LeaveRequest::create([
        'employee_id' => $employee->getKey(),
        'type' => 'vacation',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'status' => 'approved',
    ]);
    $updatedStart = $startDate->copy()->setTime(9, 0);
    $updatedEnd = $startDate->copy()->setTime(13, 30);

    Livewire::test(EditLeaveRequest::class, ['record' => $request->getRouteKey()])
        ->assertFormSet([
            'duration' => 'specific_hours',
            'date_time_range' => adminDateRange($startDate, $endDate, includesTime: true),
        ])
        ->fillForm([
            'date_time_range' => adminDateRange($updatedStart, $updatedEnd, includesTime: true),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $request->refresh();

    expect($request->start_date->format('Y-m-d H:i'))->toBe($updatedStart->format('Y-m-d H:i'))
        ->and($request->end_date->format('Y-m-d H:i'))->toBe($updatedEnd->format('Y-m-d H:i'));
});

it('rejects weekend endpoints from the admin range field', function (): void {
    signInForAdminLeaveRequests();
    $employee = adminLeaveEmployee();
    $startDate = today()->next(CarbonInterface::SATURDAY);
    $endDate = $startDate->copy()->addDays(2);

    Livewire::test(CreateLeaveRequest::class)
        ->fillForm([
            'employee_id' => $employee->getKey(),
            'type' => 'vacation',
            'duration' => 'full_day',
            'date_range' => adminDateRange($startDate, $endDate),
            'status' => 'pending',
        ])
        ->call('create')
        ->assertHasFormErrors(['date_range']);
});
