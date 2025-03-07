<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_number' => $this->trip_number,
            'status' => $this->status,
            'scheduled_date' => $this->scheduled_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'notes' => $this->notes,
            'driver' => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'email' => $this->driver->email,
                'phone' => $this->driver->phone,
            ],
            'stops' => $this->orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'stop_number' => $order->stop_number,
                    'status' => $order->status,
                    // 'customer' => [
                    //     'name' => $order->customer->name,
                    //     'phone' => $order->customer->phone,
                    //     'email' => $order->customer->email,
                    // ],
                    'location' => [
                        'name' => $order->location->name,
                        'full_address' => implode(', ', array_filter([
                            $order->location->address_line1,
                            $order->location->city,
                            $order->location->state,
                            $order->location->postal_code
                        ])),
                        'address' => $order->location->address_line1,
                        'city' => $order->location->city,
                        'state' => $order->location->state,
                        'postal_code' => $order->location->postal_code,
                        'latitude' => $order->location->latitude,
                        'longitude' => $order->location->longitude,
                    ],
                    'products' => $order->orderProducts->map(function ($orderProduct) {
                        return [
                            'id' => $orderProduct->id,
                            'quantity' => $orderProduct->fill_load ? 'Fill Load' : $orderProduct->quantity,
                            'sku' => $orderProduct->product->sku,
                            'product_name' => $orderProduct->product->name,
                            'notes' => $orderProduct->notes,
                            'delivery_notes' => $orderProduct->delivery_notes,
                            'quantity_delivered' => $orderProduct->quantity_delivered,
                        ];
                    }),
                    'arrival_time' => $order->arrived_at,
                    'delivery_time' => $order->delivered_at,
                    'signature' => $order->signature_path ? Storage::url($order->signature_path) : null,
                    'delivery_notes' => $order->delivery_notes,
                    'special_instructions' => $order->special_instructions,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
