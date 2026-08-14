<?php

namespace App\Filament\Widgets;

use App\Support\OfficeManagerDashboard;
use Filament\Widgets\Widget;

class PlanningOpportunitiesWidget extends Widget
{
    protected string $view = 'filament.widgets.planning-opportunities-widget';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'opportunities' => app(OfficeManagerDashboard::class)->planningOpportunities(),
        ];
    }
}
