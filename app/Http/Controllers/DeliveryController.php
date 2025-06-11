<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DeliveryController extends Controller
{
    public function show(Request $request, Order $order)
    {
        try {
            // Add debugging information
            Log::info('Delivery show request:', [
                'order_id' => $order->id,
                'request_url' => $request->fullUrl(),
                'query_params' => $request->query->all(),
                'headers' => $request->headers->all()
            ]);
            
            // Signed URL middleware handles authentication automatically
            
            // Load order with relationships needed for delivery
            $order->load([
                'location.preferredDeliveryContact',
                'orderProducts.product'
            ]);

            // Format the response for the PWA
            return response()->json([
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'signature_url' => $order->signature_path ? Storage::disk('r2')->url($order->signature_path) : null,
                'delivery_notes' => $order->delivery_notes,
                'delivered_at' => $order->delivered_at,
                'location' => [
                    'name' => $order->location->name,
                    'full_address' => $order->location->address_line1 . ', ' . 
                                   $order->location->city . ', ' . 
                                   $order->location->state . ' ' . 
                                   $order->location->postal_code,
                    'address_line1' => $order->location->address_line1,
                    'city' => $order->location->city,
                    'state' => $order->location->state,
                    'postal_code' => $order->location->postal_code,
                    'phone' => $order->location->phone,
                    'contact' => $order->location->preferredDeliveryContact ? [
                        'name' => $order->location->preferredDeliveryContact->name,
                        'phone' => $order->location->preferredDeliveryContact->phone,
                        'mobile_phone' => $order->location->preferredDeliveryContact->mobile_phone,
                        'phone_extension' => $order->location->preferredDeliveryContact->phone_extension,
                    ] : null,
                ],
                'order_products' => $order->orderProducts->map(function ($orderProduct) {
                    return [
                        'id' => $orderProduct->id,
                        'sku' => $orderProduct->product->sku,
                        'display_name' => $orderProduct->display_name,
                        'quantity' => $orderProduct->quantity,
                        'quantity_delivered' => $orderProduct->quantity_delivered,
                        'fill_load' => $orderProduct->fill_load,
                        'product_id' => $orderProduct->product_id,
                    ];
                })
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Accept');

        } catch (\Exception $e) {
            Log::error('Delivery show error:', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to load delivery information',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500)->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Accept');
        }
    }

    public function complete(Request $request, Order $order)
    {
        try {
            // Signed URL middleware handles authentication automatically
            
            $request->validate([
                'signature' => 'required|string',
                'notes' => 'nullable|string',
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer',
                'products.*.quantity_delivered' => 'required|numeric|min:0',
                'photos' => 'nullable|array'
            ]);

            // Save signature
            if ($request->signature) {
                $signatureData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->signature));
                $signaturePath = 'signatures/order_' . $order->id . '_' . time() . '.png';
                Storage::disk('r2')->put($signaturePath, $signatureData);
                
                $order->update(['signature_path' => $signaturePath]);
            }

            // Update delivered quantities
            foreach ($request->products as $productData) {
                $order->orderProducts()
                    ->where('id', $productData['product_id'])
                    ->update(['quantity_delivered' => $productData['quantity_delivered']]);
            }

            // Save photos
            if ($request->photos) {
                foreach ($request->photos as $index => $photoData) {
                    $photoBase64 = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $photoData));
                    $photoPath = 'delivery_photos/order_' . $order->id . '_' . time() . '_' . $index . '.jpg';
                    Storage::disk('r2')->put($photoPath, $photoBase64);
                }
            }

            // Update order status and delivery info
            $order->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'delivery_notes' => $request->notes
            ]);

            return response()->json([
                'message' => 'Delivery completed successfully',
                'delivered_at' => $order->delivered_at
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Accept');

        } catch (\Exception $e) {
            Log::error('Delivery completion error:', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to complete delivery'
            ], 500)->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Accept');
        }
    }
} 