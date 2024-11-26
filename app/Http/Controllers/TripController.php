<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\TripResource;

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
                    'orders' => fn($q) => $q->orderBy('stop_number', 'asc'),
                    'orders.customer',
                    'orders.location',
                    'orders.orderProducts.product'
                ])
                ->get();

            return TripResource::collection($trips);
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
            $trip->load([
                'driver',
                'orders' => fn($q) => $q->orderBy('stop_number', 'asc'),
                'orders.customer',
                'orders.location',
                'orders.orderProducts.product'
            ]);

            return new TripResource($trip);
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
