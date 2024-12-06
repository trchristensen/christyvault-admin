<?php

use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\DeliveryTagController;
use App\Http\Controllers\LeaveCalendarFeedController;
use App\Http\Controllers\KanbanCardController;
use App\Http\Controllers\NotificationController;
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

Route::get('/kanban-cards/{kanbanCard}/qr-code', [KanbanCardController::class, 'downloadQrCode'])
    ->name('kanban-cards.qr-code');

Route::get('/kanban-cards/scan/{id}', [KanbanCardController::class, 'scan'])
    ->name('kanban-cards.scan');

Route::middleware(['auth'])->group(function () {
    Route::post('/notifications/mark-as-read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read');
});
