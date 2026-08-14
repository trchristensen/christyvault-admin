<?php

namespace App\Filament\Widgets;

use App\Support\OfficeManagerDashboard;
use Filament\Widgets\Widget;

class TodayTomorrowBriefingWidget extends Widget
{
    protected string $view = 'filament.widgets.today-tomorrow-briefing-widget';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $dashboard = app(OfficeManagerDashboard::class);

        return [
            'days' => $dashboard->dayBriefings(),
            'calendarDays' => $dashboard->upcomingCalendarDaysThisWeek(),
        ];
    }
}
