<?php

namespace App\Livewire;

use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Support\Str;
use Livewire\Component;

class TestCalendarComponent extends Component
{
    public string $validStart;
    public string $validEnd;

    protected function getEventColor(Order $order): string
    {
        $status = OrderStatus::from($order->status);
        return $status->color();
    }

    public function render()
    {
        // Get orders for the calendar
        $events = Order::query()
            ->with(['customer', 'orderProducts.product'])
            ->get()
            ->map(function (Order $order) {
                $isLocked = in_array($order->status, ['in_progress', 'completed', 'delivered']);
                return [
                    'id' => $order->id,
                    'title' => $order->customer?->name ?? $order->order_number,
                    'start' => $order->assigned_delivery_date?->format('Y-m-d') ?? $order->requested_delivery_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => $order->assigned_delivery_date ? $this->getEventColor($order) : 'grey',
                    'borderColor' => 'transparent',
                    'editable' => !$isLocked,
                    'extendedProps' => [
                        'customerName' => $order->customer?->name,
                        'requestedDate' => $order->requested_delivery_date->format('m/d'),
                        'status' => Str::headline($order->status),
                        'isLocked' => $isLocked,
                        'products' => $order->orderProducts->map(function ($orderProduct) {
                            return [
                                'quantity' => $orderProduct->quantity,
                                'sku' => $orderProduct->product->sku,
                                'fill_load' => $orderProduct->fill_load
                            ];
                        })->toArray(),
                    ],
                ];
            })
            ->toArray();

        return view('livewire.test-calendar-component', [
            'events' => $events,
        ]);
    }
}
