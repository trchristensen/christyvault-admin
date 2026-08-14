<?php

namespace App\Filament\Widgets;

use App\Support\DeliveryWeatherDashboard;
use Filament\Widgets\Widget;

class WeeklyDeliveryWeatherWidget extends Widget
{
    protected string $view = 'filament.widgets.weekly-delivery-weather-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return app(DeliveryWeatherDashboard::class)->week();
    }
}
