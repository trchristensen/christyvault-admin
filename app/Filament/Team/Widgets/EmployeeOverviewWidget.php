<?php

namespace App\Filament\Team\Widgets;

use App\Filament\Team\Resources\LeaveRequestResource;
use App\Models\CalendarDay;
use App\Models\LeaveRequest;
use Filament\Widgets\Widget;

class EmployeeOverviewWidget extends Widget
{
    protected string $view = 'filament.team.widgets.employee-overview-widget';

    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $calendarDays = CalendarDay::query()
            ->whereDate('date', '>=', today()->toDateString())
            ->whereDate('date', '<=', today()->addDays(90)->toDateString())
            ->orderBy('date')
            ->orderBy('name')
            ->limit(6)
            ->get();

        $user = auth()->user();
        $employee = $user?->employee;
        $showsTeamTimeOff = $user?->canViewTeamTimeOffOverview() ?? false;
        $leaveRequests = $showsTeamTimeOff
            ? LeaveRequest::query()
                ->with('employee')
                ->whereHas('employee', fn ($query) => $query->where('is_active', true))
                ->whereDate('end_date', '>=', today()->toDateString())
                ->whereIn('status', ['pending', 'approved'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('start_date')
                ->orderBy('employee_id')
                ->get()
            : ($employee?->leaveRequests()
                ->whereDate('end_date', '>=', today()->toDateString())
                ->whereIn('status', ['pending', 'approved'])
                ->orderBy('start_date')
                ->get() ?? collect());

        return [
            'calendarDays' => $calendarDays,
            'employee' => $employee,
            'leaveRequests' => $leaveRequests,
            'showsTeamTimeOff' => $showsTeamTimeOff,
            'timeOffRequestUrl' => $employee
                ? LeaveRequestResource::getUrl('create', panel: 'team')
                : null,
        ];
    }
}
