<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
                                'product_name' => $orderProduct->product->name,
                                'sku' => $orderProduct->product->sku,
                            ];
                        });

                        return [
                            'stop_number' => $order->stop_number,
                            'customer_name' => $order->customer->name,
                            'location' => [
                                'name' => $order->location->name,
                                'full_address' => $order->location->full_address,
                                'address' => $order->location->address_line1,
                                'city' => $order->location->city,
                                'state' => $order->location->state,
                                'postal_code' => $order->location->postal_code,
                                'latitude' => $order->location->latitude,
                                'longitude' => $order->location->longitude,
                            ],
                            'products' => $products,
                            'status' => $order->status,
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
                                        'product_id' => $orderProduct->product_id,
                                        'quantity' => $orderProduct->fill_load ? 'Fill Load' : $orderProduct->quantity,
                                        'sku' => $orderProduct->product->sku,
                                        'product_name' => $orderProduct->product->name,
                                        'notes' => $orderProduct->notes,
                                        'delivery_notes' => $orderProduct->delivery_notes
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
            $trip = Trip::where('id', $trip->id)
                ->with([
                    'driver',
                    'orders' => function ($query) {
                        $query->orderBy('stop_number', 'asc');
                    },
                    'orders.customer',
                    'orders.location',
                    'orders.orderProducts.product'
                ])
                ->first();

            if (!$trip) {
                return response()->json(['error' => 'Trip not found'], 404);
            }

            $deliveryDetails = $trip->orders->map(function ($order) {
                return [
                    'stop_number' => $order->stop_number,
                    'customer_name' => $order->customer->name,
                    'customer_phone' => $order->customer->phone,
                    'location' => [
                        'name' => $order->location->name,
                        'address' => $order->location->address_line1,
                        'city' => $order->location->city,
                        'state' => $order->location->state,
                        'postal_code' => $order->location->postal_code,
                        'latitude' => $order->location->latitude,
                        'longitude' => $order->location->longitude,
                        'full_address' => implode(', ', array_filter([
                            $order->location->address_line1,
                            $order->location->city,
                            $order->location->state,
                            $order->location->postal_code
                        ]))
                    ],
                    'products' => $order->orderProducts->map(function ($orderProduct) {
                        return [
                            'quantity' => $orderProduct->fill_load ? 'Fill Load' : $orderProduct->quantity,
                            'sku' => $orderProduct->product->sku,
                            'product_name' => $orderProduct->product->name,
                            'notes' => $orderProduct->notes
                        ];
                    }),
                    'status' => $order->status ?? 'pending',
                    'arrival_time' => $order->arrived_at,
                    'delivery_time' => $order->delivered_at,
                    'signature' => $order->signature_path ? Storage::url($order->signature_path) : null,
                    'delivery_notes' => $order->delivery_notes,
                    'special_instructions' => $order->special_instructions,
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
                'driver' => [
                    'id' => $trip->driver->id,
                    'name' => $trip->driver->name,
                    'email' => $trip->driver->email,
                    'phone' => $trip->driver->phone,
                ],
                'delivery_details' => $deliveryDetails,
                'orders' => $trip->orders,
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

    public function updateStatus(Request $request, Trip $trip)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,in_progress,completed,cancelled',
                'completion_note' => 'nullable|string'
            ]);

            $updates = [
                'status' => $request->status,
                'notes' => $request->completion_note
                    ? ($trip->notes ? $trip->notes . "\n\n" : '') . "Completion Note: " . $request->completion_note
                    : $trip->notes
            ];

            if ($request->status === 'in_progress' && !$trip->start_time) {
                $updates['start_time'] = now();
            }

            if ($request->status === 'completed' && !$trip->end_time) {
                $updates['end_time'] = now();
            }

            $trip->update($updates);

            return response()->json([
                'message' => 'Trip status updated successfully',
                'status' => $trip->status,
                'start_time' => $trip->start_time,
                'end_time' => $trip->end_time
            ]);
        } catch (\Exception $e) {
            Log::error('Trip status update error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to update trip status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function markStopArrival(Request $request, Trip $trip, $stopNumber)
    {
        try {
            $order = $trip->orders()
                ->where('stop_number', $stopNumber)
                ->firstOrFail();

            $order->update([
                'arrived_at' => now(),
                'status' => 'arrived'
            ]);

            return response()->json([
                'message' => 'Stop arrival marked successfully',
                'arrived_at' => $order->arrived_at
            ]);
        } catch (\Exception $e) {
            Log::error('Mark stop arrival error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to mark stop arrival',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function completeStop(Request $request, Trip $trip, $stopNumber)
    {
        try {
            $order = $trip->orders()
                ->where('stop_number', $stopNumber)
                ->firstOrFail();

            $order->update([
                'delivered_at' => now(),
                'status' => 'completed'
            ]);

            return response()->json([
                'message' => 'Stop completed successfully',
                'delivered_at' => $order->delivered_at
            ]);
        } catch (\Exception $e) {
            Log::error('Complete stop error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to complete stop',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadSignature(Request $request, Trip $trip, $stopNumber)
    {
        try {
            $request->validate([
                'signature' => 'required|string'  // Base64 encoded signature
            ]);

            // Generate a unique filename for the signature
            $filename = 'signature_' . $trip->id . '_' . $stopNumber . '_' . time() . '.png';
            $path = 'signatures/' . $filename;

            // Decode base64 and save to storage
            $signatureData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->signature));
            Storage::disk('public')->put($path, $signatureData);

            $order = $trip->orders()
                ->where('stop_number', $stopNumber)
                ->firstOrFail();

            $order->update([
                'signature_path' => $path
            ]);

            return response()->json([
                'message' => 'Signature uploaded successfully',
                'signature_path' => Storage::url($path)
            ]);
        } catch (\Exception $e) {
            Log::error('Upload signature error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to upload signature',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateDeliveredQuantities(Request $request, Trip $trip, $stopNumber)
    {
        try {
            $order = $trip->orders()
                ->where('stop_number', $stopNumber)
                ->firstOrFail();

            $request->validate([
                'products' => 'required|array',
                'products.*.id' => 'required|exists:order_product,id',
                'products.*.quantity_delivered' => 'required|integer|min:0',
                'products.*.delivery_notes' => 'nullable|string'
            ]);

            foreach ($request->products as $product) {
                $orderProduct = $order->orderProducts()->findOrFail($product['id']);
                $orderProduct->update([
                    'quantity_delivered' => $product['quantity_delivered'],
                    'delivery_notes' => $product['delivery_notes'] ?? null
                ]);
            }

            return response()->json([
                'message' => 'Delivery quantities updated successfully',
                'order' => $order->load('orderProducts.product')
            ]);
        } catch (\Exception $e) {
            Log::error('Update delivered quantities error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to update delivered quantities',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
