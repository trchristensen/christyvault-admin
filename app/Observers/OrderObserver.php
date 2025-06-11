<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(
        private SmsService $smsService
    ) {}

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        //
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

        // Check if order status changed to important statuses
        if ($order->wasChanged('status')) {
            $this->handleStatusChange($order);
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }

    /**
     * Handle trip assignment (driver gets assigned)
     */
    private function handleTripAssignment(Order $order): void
    {
        if (!config('sms.driver_notifications.order_assignments')) {
            return;
        }

        try {
            // Load the trip with driver
            $order->load('trip.driver');
            
            if ($order->trip && $order->trip->driver) {
                Log::info('Sending order assignment SMS', [
                    'order_id' => $order->id,
                    'driver_id' => $order->trip->driver->id
                ]);

                $this->smsService->sendOrderAssignment($order->trip->driver, $order);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send order assignment SMS', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle status changes
     */
    private function handleStatusChange(Order $order): void
    {
        if (!config('sms.driver_notifications.status_updates')) {
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
                    $statusMessage = "Order #{$order->order_number} status updated to: " . 
                                   ucfirst(str_replace('_', ' ', $status));
                    
                    if ($status === 'ready_for_delivery') {
                        $deliveryUrl = app(SmsService::class)->generateShortDeliveryLink($order);
                        $statusMessage .= "\n\nDelivery Link: {$deliveryUrl}";
                    }

                    Log::info('Sending status update SMS', [
                        'order_id' => $order->id,
                        'status' => $status,
                        'driver_id' => $order->trip->driver->id
                    ]);

                    $this->smsService->sendSms($order->trip->driver->phone, $statusMessage);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send status update SMS', [
                    'order_id' => $order->id,
                    'status' => $status,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
