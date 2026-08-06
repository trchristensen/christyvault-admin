<?php

namespace App\Filament\Team\Widgets;

use App\Filament\Team\Resources\LeaveRequestResource;
use App\Models\CalendarDay;
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

        $employee = auth()->user()?->employee;
        $leaveRequests = $employee?->leaveRequests()
            ->whereDate('end_date', '>=', today()->toDateString())
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('start_date')
            ->limit(4)
            ->get() ?? collect();

        return [
            'calendarDays' => $calendarDays,
            'employee' => $employee,
            'leaveRequests' => $leaveRequests,
            'timeOffRequestUrl' => $employee
                ? LeaveRequestResource::getUrl('create', panel: 'team')
                : null,
        ];
    }
}
