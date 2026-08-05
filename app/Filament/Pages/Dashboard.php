<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected ?string $subheading = 'Your daily briefing across deliveries, employees, and customer follow-up.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deliveryCalendar')
                ->label('Delivery calendar')
                ->icon('heroicon-m-calendar-days')
                ->color('gray')
                ->url(OrderResource::getUrl('calendar')),
            Action::make('newOrder')
                ->label('New order')
                ->icon('heroicon-m-plus')
                ->url(OrderResource::getUrl('create')),
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
