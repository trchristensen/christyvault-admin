<?php

use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\DeliveryCalendarPrintController;
use App\Http\Controllers\DeliveryTagController;
use App\Http\Controllers\LeaveCalendarFeedController;
use App\Http\Controllers\KanbanCardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\OrderController;
use App\Models\KanbanCard;
use Illuminate\Support\Facades\Route;



// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/orders/calendar/print', [DeliveryCalendarPrintController::class, 'view'])->name('delivery-calendar.print');
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