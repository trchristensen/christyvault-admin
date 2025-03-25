<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Duplicate an order and its products
     *
     * @param Order $record
     * @return \Illuminate\Http\RedirectResponse
     */
    public function duplicate(Order $record)
    {
        // Create a duplicate order
        $newOrder = $record->replicate();
        $newOrder->status = OrderStatus::PENDING;
        $newOrder->order_date = now();
        $newOrder->created_at = now();
        $newOrder->save();
        
        // Duplicate order products
        foreach ($record->orderProducts as $product) {
            $newProduct = $product->replicate();
            $newProduct->order_id = $newOrder->id;
            $newProduct->save();
        }
        
        // Redirect to the Filament admin panel edit page
        return redirect(route('filament.admin.resources.orders.edit', $newOrder));
    }
} 