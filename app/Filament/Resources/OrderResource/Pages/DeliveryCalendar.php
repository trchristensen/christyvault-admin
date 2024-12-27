<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Saade\FilamentFullCalendar\Components\FullCalendarComponent;

class DeliveryCalendar extends Page
{
    protected static string $resource = OrderResource::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Delivery Management';
    protected static ?string $navigationLabel = 'Delivery Calendar';
    // protected static ?int $navigationSort = ; // Adjust this number to change the order in the sidebar
    protected static ?string $slug = 'delivery-calendar';
    protected static string $view = 'filament.resources.order-resource.pages.delivery-calendar';

    public function getTitle(): string
    {
        return 'Delivery Calendar';
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Print Calendar')
                ->url(route('delivery-calendar.print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->openUrlInNewTab(),
        ];
    }
}
