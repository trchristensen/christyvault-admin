<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\Page;
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

    protected function getHeaderActions(): array
    {
        return [
            // Add any actions you want in the header
        ];
    }

    protected function getCalendarOptions(): array
    {
        return [
            'initialView' => 'dayGridMonth',
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay',
            ],
        ];
    }

    public function getCalendarEvents(): array
    {
        return Order::query()
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'title' => "Order #{$order->order_number} - " . ($order->customer ? $order->customer->name : 'No Customer'),
                    'start' => $order->requested_delivery_date,
                    // 'url' => route('filament.resources.orders.edit', $order),
                    'backgroundColor' => $this->getEventColor($order),
                    'borderColor' => $this->getEventColor($order),
                ];
            })
            ->toArray();
    }

    protected function getEventColor(Order $order): string
    {
        return match ($order->status) {
            'pending' => '#FFA500',
            'confirmed' => '#4169E1',
            'in_production' => '#32CD32',
            'ready_for_delivery' => '#9370DB',
            'out_for_delivery' => '#1E90FF',
            'delivered' => '#228B22',
            'cancelled' => '#DC143C',
            default => '#808080',
        };
    }
}
