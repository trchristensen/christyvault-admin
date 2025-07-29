<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Enums\OrderStatus;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

class CalendarFeedController extends Controller
{
    public function download()
    {
        $calendar = Calendar::create('Christy Vault Deliveries')
            ->refreshInterval(1) // Set to 1 minute
            ->timezone('America/Los_Angeles')
            ->productIdentifier('-//Christy Vault//Delivery Calendar//EN')
            ->withoutAutoTimezoneComponents();  // Prevents timezone duplication

        Order::query()
            ->with(['location', 'orderProducts.product'])
            ->withoutTrashed()
            ->whereNotIn('status', [OrderStatus::CANCELLED])
            ->get()
            ->each(function (Order $order) use ($calendar) {
                $calendar->event(
                    Event::create()
                        ->name($order->location?->name ?? $order->order_number)
                        // ->description($this->generateDescription($order))
                        ->uniqueIdentifier($order->id . '-' . time()) // Add timestamp to UID to force refresh
                        ->createdAt($order->created_at)
                        ->startsAt($order->assigned_delivery_date ?? $order->requested_delivery_date)
                        ->endsAt(($order->assigned_delivery_date ?? $order->requested_delivery_date)?->copy()->addHours(1))
                        ->fullDay()
                );
            });

        return response($calendar->get())
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="deliveries.ics"') // Changed to inline
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('X-PUBLISHED-TTL', 'PT1M');
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
            if ($orderProduct->is_custom_product) {
                $productName = $orderProduct->custom_description ?? 'Custom Product';
            } else {
                $productName = $orderProduct->product?->sku ?? 'Unknown Product';
            }
            $description[] = "{$orderProduct->quantity}x {$productName}";
        }

        if ($order->special_instructions) {
            $description[] = "\nNotes: {$order->special_instructions}";
        }

        return implode("\n", $description);
    }
}