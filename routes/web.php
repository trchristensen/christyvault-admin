<?php

use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\DeliveryTagController;
use App\Http\Controllers\LeaveCalendarFeedController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/orders/{order}/print', [DeliveryTagController::class, 'view'])->name('orders.print');

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
