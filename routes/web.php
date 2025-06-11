<?php

use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\DeliveryCalendarPrintController;
use App\Http\Controllers\DeliveryTagController;
use App\Http\Controllers\LeaveCalendarFeedController;
use App\Http\Controllers\KanbanCardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderCalendarController;
use App\Models\KanbanCard;
use App\Models\Order;
use App\Models\Driver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;



// Secure delivery link generator
Route::get('/generate-delivery-link/{order}', function (Order $order) {
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
    $pwaUrl = 'http://localhost:8080?' . http_build_query([
        'order' => $order->id,
        'show_expires' => $showParams['expires'],
        'show_signature' => $showParams['signature'],
        'complete_expires' => $completeParams['expires'],
        'complete_signature' => $completeParams['signature']
    ]);
    
    return response()->json([
        'order' => $order->order_number,
        'customer' => $order->location->name,
        'delivery_url' => $pwaUrl,
        'show_url' => $showUrl, // Include for debugging
        'complete_url' => $completeUrl, // Include for debugging
        'expires' => now()->addDays(7)->format('Y-m-d H:i:s')
    ]);
})->middleware(['auth']);

// Test delivery link generator (for development)
Route::get('/test-delivery-links', function () {
    $orders = Order::with('location')->limit(10)->get();
    
    $links = $orders->map(function ($order) {
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
        $pwaUrl = 'http://localhost:8080?' . http_build_query([
            'order' => $order->id,
            'show_expires' => $showParams['expires'],
            'show_signature' => $showParams['signature'],
            'complete_expires' => $completeParams['expires'],
            'complete_signature' => $completeParams['signature']
        ]);
        
        return [
            'order' => $order,
            'link' => $pwaUrl,
            'show_url' => $showUrl, // Include for debugging
            'complete_url' => $completeUrl // Include for debugging
        ];
    });
    
    return view('test-delivery-links', compact('links'));
});

// Short delivery link redirect (for SMS)
Route::get('/delivery/{order}/{token}', function (Order $order, string $token) {
    // Verify token if needed (you might want to add delivery_token to orders table)
    
    // Generate the full PWA URL with signed parameters
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
    
    return redirect($pwaUrl);
})->name('delivery.redirect');

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/orders/calendar/print', [DeliveryCalendarPrintController::class, 'view'])->name('delivery-calendar.print');
Route::get('/orders/{order}/print', [DeliveryTagController::class, 'view'])->name('orders.print');
Route::get('/orders/{order}/print-formbg', [DeliveryTagController::class, 'viewWithFormBg'])->name('orders.print.formbg');


Route::get('/calendar-events', [OrderCalendarController::class, 'events']);
Route::post('/orders/assign-date', [OrderCalendarController::class, 'assignDate']);
Route::post('/orders/unassign-date', [OrderCalendarController::class, 'unassignDate']);

// routes/web.php
Route::get('calendar/feed/{token}', [CalendarFeedController::class, 'download'])
    ->name('calendar.feed')
    ->middleware('signed');


Route::get('calendar', fn() => view('calendar', [
    'url' => auth()->user()?->getCalendarFeedUrl()
]));

Route::get('calendar/leave-feed/{token}', [LeaveCalendarFeedController::class, 'download'])
    ->name('calendar.leave-feed')
    ->middleware('signed');

Route::get('/kanban-cards/{kanbanCard}/qr-code', [KanbanCardController::class, 'downloadQrCode'])
    ->name('kanban-cards.qr-code');

Route::get('kanban-cards/{id}/scan', [KanbanCardController::class, 'scan'])->name('kanban-cards.scan');
Route::post('kanban-cards/{id}/scan', [KanbanCardController::class, 'scan']);


Route::get('/kanban-cards/{kanbanCard}/print', [KanbanCardController::class, 'print'])
    ->name('kanban-cards.print');

Route::get('/kanban-cards/{kanbanCard}/component', function (KanbanCard $kanbanCard) {
    return view('components.printable-kanban-card', [
        'kanbanCard' => $kanbanCard,
        'size' => request('size', 'standard')
    ]);
});

Route::get('/kanban-cards/{kanbanCard}/print-label', [KanbanCardController::class, 'printLabel'])
    ->name('kanban-cards.print-label');

Route::get('/kanban-cards/print-labels-bulk', [KanbanCardController::class, 'printLabelsBulk'])
    ->name('kanban-cards.print-labels-bulk');

Route::middleware(['auth'])->group(function () {
    Route::post('/notifications/mark-as-read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read');
});

Route::get('/admin/orders/{record}/duplicate', [OrderController::class, 'duplicate'])
    ->name('filament.admin.resources.orders.duplicate')
    ->middleware(['auth']);

// Driver SMS consent form (for Telnyx verification)
Route::get('/driver-sms-consent/{driver}', function (Driver $driver) {
    return view('driver-consent', compact('driver'));
})->name('driver.sms.consent')->middleware('signed');

// Generate signed consent URLs (for admin use)
Route::get('/generate-driver-consent-links', function () {
    $drivers = Driver::with('employee')->get();
    
    $links = $drivers->map(function ($driver) {
        $url = URL::temporarySignedRoute(
            'driver.sms.consent',
            now()->addDays(30), // 30 day expiration
            ['driver' => $driver->id]
        );
        
        return [
            'driver' => $driver,
            'employee' => $driver->employee,
            'consent_url' => $url
        ];
    });
    
    return view('driver-consent-links', compact('links'));
})->middleware(['auth']);

// Handle consent form submission
Route::post('/driver-sms-consent/{driver}', function (Driver $driver, Request $request) {
    $driver->update([
        'sms_consent_given' => true,
        'sms_consent_at' => now()
    ]);
    
    return response()->json([
        'success' => true,
        'message' => 'SMS consent recorded for ' . $driver->employee->name
    ]);
})->name('driver.sms.consent.submit')->middleware('signed');