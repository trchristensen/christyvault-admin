<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Order;
use App\Models\Trip;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;

class SmsService
{
    public function __construct()
    {
        // No longer need to set SDK API key since we're using HTTP client
    }

    /**
     * Send SMS to a phone number
     */
    public function sendSms(string $to, string $message): bool
    {
        if (!config('sms.enabled')) {
            Log::info('SMS disabled, skipping message', ['to' => $to, 'message' => $message]);
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . config('sms.telnyx.api_key'),
            ])->post('https://api.telnyx.com/v2/messages', [
                'from' => config('sms.telnyx.from_number'),
                'to' => $this->formatPhoneNumber($to),
                'text' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('SMS sent successfully', [
                    'to' => $to,
                    'message_id' => $data['data']['id'] ?? 'unknown',
                    'status' => $data['data']['to'][0]['status'] ?? 'sent'
                ]);
                return true;
            } else {
                Log::error('Failed to send SMS', [
                    'to' => $to,
                    'error' => $response->body(),
                    'status' => $response->status(),
                    'message' => substr($message, 0, 100) . '...'
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send SMS', [
                'to' => $to,
                'error' => $e->getMessage(),
                'message' => substr($message, 0, 100) . '...'
            ]);

            return false;
        }
    }

    /**
     * Send daily schedule to driver
     */
    public function sendDailySchedule(Employee $driver): bool
    {
        if (!$driver->phone) {
            Log::warning('Driver has no phone number', ['driver_id' => $driver->id]);
            return false;
        }

        // Get today's orders for this driver
        $orders = Order::where('driver_id', $driver->id)
            ->whereDate('assigned_delivery_date', today())
            ->with(['location'])
            ->get();

        if ($orders->isEmpty()) {
            $message = "Good morning! You have no deliveries scheduled for today.";
            return $this->sendSms($driver->phone, $message);
        }

        $message = $this->buildDailyScheduleMessage($driver, $orders);
        return $this->sendSms($driver->phone, $message);
    }

    /**
     * Send order assignment notification
     */
    public function sendOrderAssignment(Employee $driver, Order $order): bool
    {
        if (!$driver->phone) {
            return false;
        }

        $deliveryUrl = $this->generateDeliveryLink($order);
        
        $message = "New delivery assigned!\n\n";
        $message .= "Order #{$order->order_number}\n";
        $message .= "Customer: {$order->location->name}\n";
        $message .= "Address: {$order->location->address_line1}, {$order->location->city}\n";
        $message .= "Delivery Link: {$deliveryUrl}\n\n";
        $message .= "Reply HELP for commands";

        return $this->sendSms($driver->phone, $message);
    }

    /**
     * Process incoming SMS message
     */
    public function processIncomingSms(string $from, string $message): ?string
    {
        // Find driver by phone number
        $driver = Employee::where('phone', $this->formatPhoneNumber($from))->first();
        
        if (!$driver) {
            return "Driver not found. Please contact your supervisor.";
        }

        // Parse message for commands
        $message = trim(strtoupper($message));
        
        if (str_starts_with($message, 'HELP')) {
            return $this->getHelpMessage();
        }
        
        if (str_starts_with($message, 'ORDER ')) {
            $orderNumber = trim(substr($message, 6));
            return $this->getOrderDetails($driver, $orderNumber);
        }
        
        if (str_starts_with($message, 'STATUS ')) {
            $orderNumber = trim(substr($message, 7));
            return $this->getOrderStatus($driver, $orderNumber);
        }
        
        if (str_starts_with($message, 'DELIVERED ')) {
            $orderNumber = trim(substr($message, 10));
            return $this->markOrderDelivered($driver, $orderNumber);
        }
        
        if (str_starts_with($message, 'ORDERS') || str_starts_with($message, 'TODAY')) {
            return $this->getTodaysOrders($driver);
        }

        // Default response
        return "Unknown command. Reply HELP for available commands.";
    }

    /**
     * Build daily schedule message
     */
    private function buildDailyScheduleMessage(Employee $driver, $orders): string
    {
        $message = "Good morning {$driver->name}!\n\n";
        $message .= "Your deliveries for today:\n\n";

        foreach ($orders as $order) {
            $message .= "• Order #{$order->order_number}\n";
            $message .= "  {$order->location->name}\n";
            $message .= "  {$order->location->address_line1}, {$order->location->city}\n";
            $message .= "  Link: {$this->generateShortDeliveryLink($order)}\n\n";
        }

        $message .= "Reply HELP for commands\n";
        $message .= "Have a safe day!";

        return $message;
    }

    /**
     * Generate delivery link for order
     */
    private function generateDeliveryLink(Order $order): string
    {
        // Generate signed URLs for both show and complete endpoints
        $showUrl = URL::temporarySignedRoute(
            'delivery.show',
            now()->addDays(7),
            ['order' => $order->id]
        );
        
        $completeUrl = URL::temporarySignedRoute(
            'delivery.complete',
            now()->addDays(7),
            ['order' => $order->id]
        );
        
        // Parse both URLs to extract their signatures
        $showParts = parse_url($showUrl);
        parse_str($showParts['query'], $showParams);
        
        $completeParts = parse_url($completeUrl);
        parse_str($completeParts['query'], $completeParams);
        
        // Build the PWA URL with order ID and both signatures
        $pwaUrl = config('app.pwa_url', 'http://localhost:8080') . '?' . http_build_query([
            'order' => $order->id,
            'show_expires' => $showParams['expires'],
            'show_signature' => $showParams['signature'],
            'complete_expires' => $completeParams['expires'],
            'complete_signature' => $completeParams['signature']
        ]);
        
        return $pwaUrl;
    }

    /**
     * Generate short delivery link (for SMS character limits)
     */
    private function generateShortDeliveryLink(Order $order): string
    {
        // For SMS, we might want to use a URL shortener or simple redirect
        return route('delivery.redirect', ['order' => $order->id, 'token' => $order->delivery_token ?? str()->random(32)]);
    }

    /**
     * Get help message
     */
    private function getHelpMessage(): string
    {
        return "Available commands:\n" .
               "• ORDER [number] - Get order details\n" .
               "• STATUS [number] - Check order status\n" .
               "• DELIVERED [number] - Mark as delivered\n" .
               "• ORDERS or TODAY - Today's orders\n" .
               "• HELP - Show this message";
    }

    /**
     * Get order details
     */
    private function getOrderDetails(Employee $driver, string $orderNumber): string
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('driver_id', $driver->id)
            ->with(['location', 'orderProducts.product'])
            ->first();

        if (!$order) {
            return "Order #{$orderNumber} not found or not assigned to you.";
        }

        $message = "Order #{$order->order_number}\n";
        $message .= "Customer: {$order->location->name}\n";
        $message .= "Address: {$order->location->address_line1}, {$order->location->city}\n";
        $message .= "Status: " . ucfirst(str_replace('_', ' ', $order->status)) . "\n\n";
        
        $message .= "Products:\n";
        foreach ($order->orderProducts as $product) {
            $qty = $product->fill_load ? 'Fill Load' : $product->quantity;
            $message .= "• {$product->product->name} - {$qty}\n";
        }

        $deliveryUrl = $this->generateShortDeliveryLink($order);
        $message .= "\nDelivery Link: {$deliveryUrl}";

        return $message;
    }

    /**
     * Get order status
     */
    private function getOrderStatus(Employee $driver, string $orderNumber): string
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('driver_id', $driver->id)
            ->first();

        if (!$order) {
            return "Order #{$orderNumber} not found or not assigned to you.";
        }

        $status = ucfirst(str_replace('_', ' ', $order->status));
        $message = "Order #{$order->order_number} Status: {$status}";
        
        if ($order->delivered_at) {
            $message .= "\nDelivered: " . $order->delivered_at->format('M j, Y g:i A');
        }

        return $message;
    }

    /**
     * Mark order as delivered (simplified version)
     */
    private function markOrderDelivered(Employee $driver, string $orderNumber): string
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('driver_id', $driver->id)
            ->first();

        if (!$order) {
            return "Order #{$orderNumber} not found or not assigned to you.";
        }

        if ($order->status === 'delivered') {
            return "Order #{$orderNumber} is already marked as delivered.";
        }

        // Simple delivery marking - for full delivery with signature/photos, use the PWA
        $order->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'delivery_notes' => 'Marked delivered via SMS'
        ]);

        return "Order #{$orderNumber} marked as delivered. Use the delivery link for full completion with signature and photos.";
    }

    /**
     * Get today's orders for driver
     */
    private function getTodaysOrders(Employee $driver): string
    {
        $orders = Order::where('driver_id', $driver->id)
            ->whereDate('assigned_delivery_date', today())
            ->with('location')
            ->get();

        if ($orders->isEmpty()) {
            return "You have no deliveries scheduled for today.";
        }

        $message = "Today's Orders:\n\n";
        foreach ($orders as $order) {
            $status = ucfirst(str_replace('_', ' ', $order->status));
            $message .= "#{$order->order_number} - {$order->location->name}\n";
            $message .= "Status: {$status}\n";
            $message .= "{$order->location->address_line1}, {$order->location->city}\n\n";
        }

        $message .= "Reply ORDER [number] for details";

        return $message;
    }

    /**
     * Format phone number for consistency
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if missing (assuming US)
        if (strlen($phone) === 10) {
            $phone = '1' . $phone;
        }
        
        // Add + prefix
        return '+' . $phone;
    }
} 