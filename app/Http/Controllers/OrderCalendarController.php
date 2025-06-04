<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;

class OrderCalendarController extends Controller
{
    public function events(Request $request)
    {
        // Eager load 'location' relationship
        $orders = Order::with('location')->whereNotNull('assigned_delivery_date')->get();

        // Group orders by date first
        $groupedOrders = [];
        foreach ($orders as $order) {
            $date = $order->assigned_delivery_date->format('Y-m-d');
            if (!isset($groupedOrders[$date])) {
                $groupedOrders[$date] = [];
            }
            $groupedOrders[$date][] = $order;
        }

        $events = [];
        
        // Process each day's orders
        foreach ($groupedOrders as $date => $dateOrders) {
            // Simple sort by plant location (alphabetical will put colma_main before colma_locals)
            usort($dateOrders, function($a, $b) {
                $locA = $a->plant_location ?? 'colma_main';
                $locB = $b->plant_location ?? 'colma_main';
                
                // Custom sort to ensure colma_main comes before colma_locals
                if ($locA === 'colma_main' && $locB === 'colma_locals') return -1;
                if ($locA === 'colma_locals' && $locB === 'colma_main') return 1;
                
                return strcmp($locA, $locB);
            });
            
            // Track when plant location changes within the day
            $lastPlantLocation = null;
            $sortOrder = 0; // Add explicit sort order counter
            foreach ($dateOrders as $order) {
                $plantLocation = $order->plant_location ?? 'colma_main';
                $isGroupStart = ($lastPlantLocation !== $plantLocation);
                $lastPlantLocation = $plantLocation;
                $sortOrder++; // Increment for each order

                $events[] = [
                    'id'    => $order->id,
                    'title' => optional($order->location)->name ?? $order->order_number,
                    'start' => $order->assigned_delivery_date->toDateString(),
                    'allDay' => true,
                    'sort_order' => $sortOrder, // Add explicit sort order
                    'extendedProps' => [
                        'location_line1' => optional($order->location)->address_line1,
                        'location_line2' => optional($order->location) ? "{$order->location->city}, {$order->location->state}" : '',
                        'status' => $order->status,
                        'order_number' => $order->order_number,
                        'requested_delivery_date' => $order->requested_delivery_date,
                        'delivered_at' => $order->delivered_at,
                        'order_date' => $order->order_date,
                        'plant_location' => $plantLocation,
                        'is_group_start' => $isGroupStart,
                        'sort_order' => $sortOrder, // Also in extendedProps for debugging
                    ],
                ];
            }
        }

        return response()->json($events);
    }

    public function assignDate(Request $request)
    {
        $order = Order::with('location')->findOrFail($request->order_id);
        $order->assigned_delivery_date = $request->assigned_delivery_date;
        $order->save();

        // We need to recalculate the sort order and group start for this date
        // Get all orders for this date to determine proper placement
        $dateOrders = Order::with('location')
            ->whereDate('assigned_delivery_date', $request->assigned_delivery_date)
            ->get();

        // Sort them the same way as in events()
        $dateOrders = $dateOrders->sortBy(function($order) {
            $loc = $order->plant_location ?? 'colma_main';
            if ($loc === 'colma_main') return '1';
            if ($loc === 'colma_locals') return '2';
            if ($loc === 'tulare_plant') return '3';
            return '4';
        })->values();

        // Find this order's position and determine is_group_start
        $sortOrder = 1;
        $isGroupStart = false;
        $lastPlantLocation = null;
        
        foreach ($dateOrders as $index => $dateOrder) {
            if ($dateOrder->id === $order->id) {
                $plantLocation = $dateOrder->plant_location ?? 'colma_main';
                $isGroupStart = ($lastPlantLocation !== $plantLocation);
                $sortOrder = $index + 1;
                break;
            }
            $lastPlantLocation = $dateOrder->plant_location ?? 'colma_main';
        }

        // Return the event data for updating the calendar
        $event = [
            'id'    => $order->id,
            'title' => optional($order->location)->name ?? $order->order_number,
            'start' => optional($order->assigned_delivery_date)->toDateString(),
            'allDay' => true,
            'sort_order' => $sortOrder,
            'extendedProps' => [
                'location_line1' => optional($order->location)->address_line1,
                'location_line2' => optional($order->location) ? "{$order->location->city}, {$order->location->state}" : '',
                'status' => $order->status,
                'order_number' => $order->order_number,
                'requested_delivery_date' => $order->requested_delivery_date,
                'delivered_at' => $order->delivered_at,
                'order_date' => $order->order_date,
                'plant_location' => $order->plant_location ?? 'colma_main',
                'is_group_start' => $isGroupStart,
                'sort_order' => $sortOrder,
            ],
        ];

        return response()->json(['success' => true, 'event' => $event]);
    }

    public function unassignDate(Request $request)
    {
        $order = Order::with('location')->findOrFail($request->order_id);
        $order->assigned_delivery_date = null;
        $order->save();

        // Return the order data for recreating the sidebar element
        $orderData = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'location_name' => optional($order->location)->name,
            'location_city' => optional($order->location)->city,
            'location_state' => optional($order->location)->state,
        ];

        return response()->json(['success' => true, 'order' => $orderData]);
    }
}