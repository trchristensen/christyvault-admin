<?php

namespace App\Observers;

use App\Models\Location;
use App\Models\Order;
use App\Services\DeliveryTripService;
use App\Services\SmsService;
use Exception;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(
        private SmsService $smsService,
        private DeliveryTripService $deliveryTrips,
    ) {}

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $this->refreshLocationAnalytics($order);
        $this->safelySynchronizeDeliveryTrip($order, ensureTrip: true);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Check if trip assignment changed (driver was assigned)
        if ($order->wasChanged('trip_id') && $order->trip_id) {
            $this->handleTripAssignment($order);
        }

        if ($order->wasChanged('trip_id')) {
            $this->safelySynchronizeTripMembership($order);
        }

        // Check if order status changed to important statuses
        if ($order->wasChanged('status')) {
            $this->handleStatusChange($order);
        }

        if ($order->wasChanged([
            'location_id',
            'status',
            'order_date',
        ])) {
            $this->refreshLocationAnalytics($order);
        }

        if (! $order->wasChanged('trip_id')
            && $order->wasChanged(['assigned_delivery_date', 'driver_id', 'status'])) {
            $this->safelySynchronizeDeliveryTrip($order);
        }

        if (! $order->wasChanged('trip_id')
            && $order->trip_id
            && $order->wasChanged(['stop_number', 'delivery_notes'])) {
            $this->safelySynchronizeTripMembership($order);
        }
    }

    private function safelySynchronizeDeliveryTrip(Order $order, bool $ensureTrip = false): void
    {
        try {
            if ($ensureTrip) {
                $this->deliveryTrips->ensureScheduledOrderHasTrip($order);

                return;
            }

            $this->deliveryTrips->synchronizeOrderSchedule($order);
        } catch (\Throwable $exception) {
            report($exception);

            Log::error('Failed to synchronize delivery trip', [
                'order_id' => $order->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function safelySynchronizeTripMembership(Order $order): void
    {
        try {
            $this->deliveryTrips->synchronizeLegacyMembershipChange($order);
        } catch (\Throwable $exception) {
            report($exception);

            Log::error('Failed to synchronize trip membership', [
                'order_id' => $order->getKey(),
                'old_trip_id' => $order->getOriginal('trip_id'),
                'trip_id' => $order->trip_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        $this->refreshLocationAnalytics($order);
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        $this->refreshLocationAnalytics($order);
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        $this->refreshLocationAnalytics($order);
    }

    private function refreshLocationAnalytics(Order $order): void
    {
        $locationIds = collect([
            $order->location_id,
            $order->getOriginal('location_id'),
        ])->filter()->unique()->values();

        if ($locationIds->isEmpty()) {
            return;
        }

        Location::query()
            ->whereKey($locationIds)
            ->get()
            ->each(fn (Location $location) => $location->updateOrderAnalytics());
    }

    /**
     * Handle trip assignment (driver gets assigned)
     */
    private function handleTripAssignment(Order $order): void
    {
        if (! config('sms.driver_notifications.order_assignments')) {
            return;
        }

        try {
            // Load the trip with driver
            $order->load('trip.driver');

            if ($order->trip && $order->trip->driver) {
                Log::info('Sending order assignment SMS', [
                    'order_id' => $order->id,
                    'driver_id' => $order->trip->driver->id,
                ]);

                $this->smsService->sendOrderAssignment($order->trip->driver, $order);
            }
        } catch (Exception $e) {
            Log::error('Failed to send order assignment SMS', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle status changes
     */
    private function handleStatusChange(Order $order): void
    {
        if (! config('sms.driver_notifications.status_updates')) {
            return;
        }

        $status = $order->status;
        $originalStatus = $order->getOriginal('status');

        // Send SMS for important status changes
        $importantStatuses = ['ready_for_delivery', 'out_for_delivery', 'arrived', 'delivered'];

        if (in_array($status, $importantStatuses) && $status !== $originalStatus) {
            try {
                $order->load('trip.driver');

                if ($order->trip && $order->trip->driver && $order->trip->driver->phone) {
                    $statusMessage = "Order #{$order->order_number} status updated to: ".
                                   ucfirst(str_replace('_', ' ', $status));

                    if ($status === 'ready_for_delivery') {
                        $deliveryUrl = app(SmsService::class)->generateShortDeliveryLink($order);
                        $statusMessage .= "\n\nDelivery Link: {$deliveryUrl}";
                    }

                    Log::info('Sending status update SMS', [
                        'order_id' => $order->id,
                        'status' => $status,
                        'driver_id' => $order->trip->driver->id,
                    ]);

                    $this->smsService->sendSms($order->trip->driver->phone, $statusMessage);
                }
            } catch (Exception $e) {
                Log::error('Failed to send status update SMS', [
                    'order_id' => $order->id,
                    'status' => $status,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
