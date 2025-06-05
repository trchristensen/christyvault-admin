<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Enums\OrderStatus;

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
            // Custom sort that groups special statuses separately from plant locations
            usort($dateOrders, function($a, $b) {
                // Get grouping keys for each order
                $groupA = $this->getGroupingKey($a);
                $groupB = $this->getGroupingKey($b);
                
                // Define sort order for groups
                $groupOrder = [
                    'colma_main' => 1,
                    'colma_locals' => 2, 
                    'tulare_plant' => 3,
                    'will_call' => 4,
                    'shipped' => 5,
                ];
                
                $orderA = $groupOrder[$groupA] ?? 99;
                $orderB = $groupOrder[$groupB] ?? 99;
                
                return $orderA <=> $orderB;
            });
            
            // Track when group changes within the day
            $lastGroup = null;
            $sortOrder = 0; // Add explicit sort order counter
            foreach ($dateOrders as $order) {
                $currentGroup = $this->getGroupingKey($order);
                $isGroupStart = ($lastGroup !== $currentGroup);
                $lastGroup = $currentGroup;
                $sortOrder++; // Increment for each order

                // Get the proper status label from enum
                $statusEnum = OrderStatus::tryFrom($order->status);
                $statusLabel = $statusEnum ? $statusEnum->label() : ucfirst(str_replace('_', ' ', $order->status));

                $events[] = [
                    'id'    => $order->id,
                    'title' => optional($order->location)->name ?? $order->order_number,
                    'start' => $order->assigned_delivery_date->toDateString(),
                    'allDay' => true,
                    'sort_order' => $sortOrder, // Add explicit sort order
                    'extendedProps' => [
                        'location_line1' => optional($order->location)->address_line1,
                        'location_line2' => optional($order->location) ? "{$order->location->city}, {$order->location->state}" : '',
                        'status' => $statusLabel,
                        'status_raw' => $order->status, // Keep raw for CSS class
                        'order_number' => $order->order_number,
                        'requested_delivery_date' => $order->requested_delivery_date,
                        'delivered_at' => $order->delivered_at,
                        'order_date' => $order->order_date,
                        'plant_location' => $order->plant_location ?? 'colma_main',
                        'grouping_key' => $currentGroup, // Add for frontend group labels
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
        $dateOrders = $dateOrders->sort(function($a, $b) {
            $groupA = $this->getGroupingKey($a);
            $groupB = $this->getGroupingKey($b);
            
            $groupOrder = [
                'colma_main' => 1,
                'colma_locals' => 2, 
                'tulare_plant' => 3,
                'will_call' => 4,
                'shipped' => 5,
            ];
            
            $orderA = $groupOrder[$groupA] ?? 99;
            $orderB = $groupOrder[$groupB] ?? 99;
            
            return $orderA <=> $orderB;
        })->values();

        // Find this order's position and determine is_group_start
        $sortOrder = 1;
        $isGroupStart = false;
        $lastGroup = null;
        
        foreach ($dateOrders as $index => $dateOrder) {
            if ($dateOrder->id === $order->id) {
                $currentGroup = $this->getGroupingKey($dateOrder);
                $isGroupStart = ($lastGroup !== $currentGroup);
                $sortOrder = $index + 1;
                break;
            }
            $lastGroup = $this->getGroupingKey($dateOrder);
        }

        // Get the proper status label from enum
        $statusEnum = OrderStatus::tryFrom($order->status);
        $statusLabel = $statusEnum ? $statusEnum->label() : ucfirst(str_replace('_', ' ', $order->status));

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
                'status' => $statusLabel,
                'status_raw' => $order->status, // Keep raw for CSS class
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

        // Get the proper status label from enum
        $statusEnum = OrderStatus::tryFrom($order->status);
        $statusLabel = $statusEnum ? $statusEnum->label() : ucfirst(str_replace('_', ' ', $order->status));

        // Return the order data for recreating the sidebar element
        $orderData = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $statusLabel,
            'status_raw' => $order->status, // Keep raw for CSS class
            'location_name' => optional($order->location)->name,
            'location_city' => optional($order->location)->city,
            'location_state' => optional($order->location)->state,
        ];

        return response()->json(['success' => true, 'order' => $orderData]);
    }

    private function getGroupingKey($order)
    {
        $status = $order->status;
        if ($status === 'will_call') return 'will_call';
        if ($status === 'shipped') return 'shipped';
        return $order->plant_location ?? 'colma_main';
    }
}