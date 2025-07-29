<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Enums\OrderStatus;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Enums\EventStatus;

class CalendarFeedController extends Controller
{
    public function download($token)
    {
        // Validate the calendar token
        $user = User::where('calendar_token', $token)->first();
        
        if (!$user) {
            abort(404, 'Invalid calendar token');
        }

        $calendar = Calendar::create('Christy Vault Deliveries')
            ->refreshInterval(1) // Set to 1 minute
            ->timezone('America/Los_Angeles')
            ->productIdentifier('-//Christy Vault//Delivery Calendar//EN')
            ->withoutAutoTimezoneComponents();  // Prevents timezone duplication

        Order::query()
            ->with(['location', 'orderProducts.product'])
            ->withoutTrashed()
            ->whereNotIn('status', [OrderStatus::CANCELLED])
            ->where(function($query) {
                $query->whereNotNull('assigned_delivery_date')
                      ->orWhereNotNull('requested_delivery_date');
            })
            ->get()
            ->each(function (Order $order) use ($calendar) {
                $deliveryDate = $order->assigned_delivery_date ?? $order->requested_delivery_date;
                
                // Skip if we somehow still don't have a date
                if (!$deliveryDate) {
                    return;
                }
                
                $calendar->event(
                    Event::create()
                        ->name($order->location?->name ?? $order->order_number)
                        ->description($this->generateDescription($order))
                        ->uniqueIdentifier($order->id . '-' . time()) // Add timestamp to UID to force refresh
                        ->createdAt($order->created_at)
                        ->startsAt($deliveryDate)
                        ->endsAt($deliveryDate->copy()->addHours(1))
                        ->fullDay()
                        ->status($order->trashed() ? EventStatus::Cancelled : EventStatus::Confirmed)
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
            $description[] = "{$orderProduct->quantity}x {$orderProduct->product->sku}";
        }

        if ($order->special_instructions) {
            $description[] = "\nNotes: {$order->special_instructions}";
        }

        return implode("\n", $description);
    }
}
