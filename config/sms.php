<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS messaging via Telnyx
    |
    */

    'enabled' => env('SMS_ENABLED', true),
    
    'telnyx' => [
        'api_key' => env('TELNYX_API_KEY'),
        'from_number' => env('TELNYX_FROM_NUMBER'),
        'webhook_secret' => env('TELNYX_WEBHOOK_SECRET'),
    ],

    'daily_schedule' => [
        'enabled' => env('SMS_DAILY_SCHEDULE_ENABLED', true),
        'time' => env('SMS_DAILY_SCHEDULE_TIME', '08:00'),
        'timezone' => env('APP_TIMEZONE', 'America/New_York'),
    ],

    'driver_notifications' => [
        'enabled' => env('SMS_DRIVER_NOTIFICATIONS', true),
        'order_assignments' => true,
        'delivery_reminders' => true,
        'status_updates' => true,
    ],

    'conversation' => [
        'enabled' => true,
        'keywords' => [
            'help' => 'Available commands: ORDER [number], STATUS [number], DELIVERED [number], HELP',
            'orders' => 'Reply with ORDER [number] to get details about a specific order',
            'status' => 'Reply with STATUS [number] to check order status',
            'delivered' => 'Reply with DELIVERED [number] to mark order as delivered',
        ],
    ],
]; 