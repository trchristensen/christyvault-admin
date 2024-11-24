<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TripController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user->employee) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $trips = Trip::where('driver_id', $user->employee->id)
                ->orderBy('scheduled_date', 'desc')
                ->with([
                    'driver',
                    'orders' => function ($query) {
                        $query->orderBy('stop_number', 'asc');
                    },
                    'orders.customer',
                    'orders.location',
                    'orders.orderProducts.product'
                ])
                ->get()
                ->map(function ($trip) {
                    // Get all orders with their products
                    $deliveryDetails = $trip->orders->map(function ($order) {

                        $products = $order->orderProducts->map(function ($orderProduct) {
                            $quantity = $orderProduct->fill_load ? 'Fill Load' : $orderProduct->quantity;
                            return [
                                'quantity' => $quantity,
                                'product_name' => $orderProduct->product->name
                            ];
                        });

                        return [
                            'stop_number' => $order->stop_number,
                            'customer_name' => $order->customer->name,
                            'location' => [
                                'name' => $order->location->name,
                                'address' => $order->location->address_line1,
                                'city' => $order->location->city,
                                'state' => $order->location->state,
                                'postal_code' => $order->location->postal_code,
                                'latitude' => $order->location->latitude,
                                'longitude' => $order->location->longitude,
                            ],
                            'products' => $products
                        ];
                    });

                    return [
                        'id' => $trip->id,
                        'trip_number' => $trip->trip_number,
                        'status' => $trip->status,
                        'scheduled_date' => $trip->scheduled_date,
                        'start_time' => $trip->start_time,
                        'end_time' => $trip->end_time,
                        'notes' => $trip->notes,
                        'driver' => [
                            'id' => $trip->driver->id,
                            'name' => $trip->driver->name,
                            'email' => $trip->driver->email,
                            'phone' => $trip->driver->phone,
                        ],
                        'delivery_details' => $deliveryDetails,
                        'orders' => $trip->orders->map(function ($order) {
                            return [
                                'id' => $order->id,
                                'order_number' => $order->order_number,
                                'stop_number' => $order->stop_number,
                                'status' => $order->status,
                                'delivery_notes' => $order->delivery_notes,
                                'special_instructions' => $order->special_instructions,
                                'customer' => [
                                    'id' => $order->customer->id,
                                    'name' => $order->customer->name,
                                    'phone' => $order->customer->phone,
                                    'email' => $order->customer->email,
                                ],
                                'location' => [
                                    'id' => $order->location->id,
                                    'name' => $order->location->name,
                                    'address_line1' => $order->location->address_line1,
                                    'address_line2' => $order->location->address_line2,
                                    'city' => $order->location->city,
                                    'state' => $order->location->state,
                                    'postal_code' => $order->location->postal_code,
                                    'latitude' => $order->location->latitude,
                                    'longitude' => $order->location->longitude,
                                ],
                                'products' => $order->orderProducts->map(function ($orderProduct) {
                                    return [
                                        'id' => $orderProduct->id,
                                        'product' => [
                                            'id' => $orderProduct->product->id,
                                            'name' => $orderProduct->product->name,
                                            'sku' => $orderProduct->product->sku,
                                        ],
                                        'quantity' => $orderProduct->quantity,
                                        'fill_load' => $orderProduct->fill_load,
                                        'notes' => $orderProduct->notes,
                                    ];
                                }),
                            ];
                        }),
                        'created_at' => $trip->created_at,
                        'updated_at' => $trip->updated_at,
                    ];
                });

            return response()->json($trips);
        } catch (\Exception $e) {
            Log::error('Trip fetch error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to fetch trips',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function show(Request $request, Trip $trip)
    {
        try {
            $user = $request->user();

            $trip = Trip::where('id', $trip->id)
                ->where('driver_id', $user->employee->id)
                ->with([
                    'driver',
                    'orders' => function ($query) {
                        $query->orderBy('stop_number', 'asc');
                    },
                    'orders.customer',
                    'orders.location',
                    'orders.orderProducts.product',
                ])
                ->first();

            if (!$trip) {
                return response()->json(['error' => 'Trip not found'], 404);
            }

            $formattedOrders = $trip->orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'stop_number' => $order->stop_number,
                    'status' => $order->status,
                    'delivery_notes' => $order->delivery_notes,
                    'special_instructions' => $order->special_instructions,
                    'customer' => [
                        'id' => $order->customer->id,
                        'name' => $order->customer->name,
                        'phone' => $order->customer->phone,
                        'email' => $order->customer->email,
                    ],
                    'location' => [
                        'id' => $order->location->id,
                        'name' => $order->location->name,
                        'address_line1' => $order->location->address_line1,
                        'address_line2' => $order->location->address_line2,
                        'city' => $order->location->city,
                        'state' => $order->location->state,
                        'postal_code' => $order->location->postal_code,
                        'latitude' => $order->location->latitude,
                        'longitude' => $order->location->longitude,
                    ],
                    'products' => $order->orderProducts->map(function ($orderProduct) {
                        return [
                            'id' => $orderProduct->id,
                            'product' => [
                                'id' => $orderProduct->product->id,
                                'name' => $orderProduct->product->name,
                                'sku' => $orderProduct->product->sku,
                            ],
                            'quantity' => $orderProduct->quantity,
                            'fill_load' => $orderProduct->fill_load,
                            'notes' => $orderProduct->notes,
                        ];
                    }),
                ];
            });

            return response()->json([
                'id' => $trip->id,
                'trip_number' => $trip->trip_number,
                'status' => $trip->status,
                'scheduled_date' => $trip->scheduled_date,
                'start_time' => $trip->start_time,
                'end_time' => $trip->end_time,
                'notes' => $trip->notes,
                'driver' => $trip->driver ? [
                    'id' => $trip->driver->id,
                    'name' => $trip->driver->name,
                    'email' => $trip->driver->email,
                    'phone' => $trip->driver->phone,
                ] : null,
                'orders' => $formattedOrders,
                'created_at' => $trip->created_at,
                'updated_at' => $trip->updated_at,
            ]);
        } catch (\Exception $e) {
            Log::error('Trip fetch error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to fetch trip details',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
