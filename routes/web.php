<?php

use App\Http\Controllers\DeliveryTagController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/orders/{order}/print', [DeliveryTagController::class, 'view'])->name('orders.print');
