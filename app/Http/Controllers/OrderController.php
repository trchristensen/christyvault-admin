<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function duplicate(Order $record): RedirectResponse
    {
        $newOrder = DB::transaction(function () use ($record): Order {
            $record->loadMissing('orderProducts');

            $newOrder = $record->replicate();
            $newOrder->forceFill([
                'status' => OrderStatus::PENDING,
                'order_date' => now(),
                'customer_order_number' => null,
                'assigned_delivery_date' => null,
                'delivery_time' => null,
                'service_date' => null,
                'arrived_at' => null,
                'delivered_at' => null,
                'driver_id' => null,
                'trip_id' => null,
                'stop_number' => null,
                'signature_path' => null,
                'is_printed' => false,
                'delivery_tag_url' => null,
            ]);
            $newOrder->save();

            foreach ($record->orderProducts as $product) {
                $newProduct = $product->replicate();
                $newProduct->forceFill([
                    'order_id' => $newOrder->getKey(),
                    'quantity_delivered' => null,
                    'planned_fill_quantity' => null,
                    'fill_plan_source' => null,
                    'fill_locked_at' => null,
                ]);
                $newProduct->save();
            }

            return $newOrder;
        });

        return redirect(route('filament.admin.resources.orders.edit', $newOrder));
    }
}
