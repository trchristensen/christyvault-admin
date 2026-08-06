<?php

use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\SmsWebhookController;
use App\Http\Controllers\TripController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function (Request $request) {
    return response()->json([
        'message' => 'Hello World',
    ]);
});

// Public routes
Route::post('/tokens/create', fn () => response()->json([
    'message' => 'Password token login is no longer available.',
], 410));

// Secure delivery routes with signed URL validation
Route::name('delivery.')->group(function () {
    // Handle CORS preflight requests
    Route::options('/orders/{order}/delivery', function () {
        return response('', 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept');
    });

    Route::options('/orders/{order}/complete', function () {
        return response('', 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept');
    });

    Route::get('/orders/{order}/delivery', [DeliveryController::class, 'show'])
        ->name('show')
        ->middleware('signed');

    Route::post('/orders/{order}/complete', [DeliveryController::class, 'complete'])
        ->name('complete')
        ->middleware('signed');
});

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
        ]);
    });

    Route::get('/trips', [TripController::class, 'index']);
    Route::get('/trips/{trip}', [TripController::class, 'show']); // Add this line
    Route::patch('/trips/{trip}/status', [TripController::class, 'updateStatus']);
    Route::patch('/trips/{trip}/stops/{stop}/arrive', [TripController::class, 'markStopArrival']);
    Route::patch('/trips/{trip}/stops/{stop}/complete', [TripController::class, 'completeStop']);
    Route::post('/trips/{trip}/stops/{stop}/signature', [TripController::class, 'uploadSignature']);
    Route::patch('/trips/{trip}/stops/{stop}/products', [TripController::class, 'updateDeliveredQuantities']);

});

// SMS Webhook Routes (public, no auth required)
Route::post('/sms/webhook', [SmsWebhookController::class, 'handleIncoming'])
    ->name('sms.webhook');

// Test SMS endpoint (debug only)
Route::get('/sms/test', [SmsWebhookController::class, 'test'])
    ->name('sms.test');
