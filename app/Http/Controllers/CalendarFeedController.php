<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

class CalendarFeedController extends Controller
{
    public function download()
    {
        $calendar = Calendar::create('Christy Vault Deliveries')
            ->refreshInterval(5) // minutes
            ->timezone('America/Los_Angeles');

        Order::query()
            ->with(['customer', 'orderProducts.product'])
            ->get()
            ->each(function (Order $order) use ($calendar) {
                $calendar->event(
                    Event::create()
                        ->name($order->customer?->name ?? $order->order_number)
                        ->description($this->generateDescription($order))
                        ->uniqueIdentifier($order->id)
                        ->createdAt($order->created_at)
                        ->startsAt($order->assigned_delivery_date ?? $order->requested_delivery_date)
                        ->endsAt(($order->assigned_delivery_date ?? $order->requested_delivery_date)->addHours(1))
                        ->fullDay()
                );
            });

        return response($calendar->get())
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="deliveries.ics"');
    }

    private function generateDescription(Order $order): string
    {
        $description = [];

        $driver = $order->driver ? $order->driver : 'unassigned';

        // Add order details
        $description[] = "Order #: {$order->order_number}";
        $description[] = "Status: {$order->status}";
        $description[] = "Driver: {$driver}";

        // Add products
        $description[] = "\nProducts:";
        foreach ($order->orderProducts as $orderProduct) {
            $description[] = "{$orderProduct->quantity}x {$orderProduct->product->sku}";
        }

        if ($order->special_instructions) {
            $description[] = "\nNotes: {$order->special_instructions}";
        }

        return implode("\n", $description);
    }
}
