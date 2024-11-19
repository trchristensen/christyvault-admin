<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // Check if user exists
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            // Check if user has a driver relationship
            $driver = $user->driver;
            if (!$driver) {
                return response()->json(['error' => 'User is not associated with a driver account'], 403);
            }

            $trips = Trip::where('driver_id', $driver->id)
                ->orderBy('scheduled_date', 'desc')
                ->with(['locations' => function ($query) {
                    $query->orderBy('locationables.sequence', 'asc');
                }])
                ->get()
                ->map(function ($trip) {
                    return [
                        'id' => $trip->id,
                        'trip_number' => $trip->trip_number,
                        'status' => $trip->status,
                        'scheduled_date' => $trip->scheduled_date,
                        'start_time' => $trip->start_time,
                        'end_time' => $trip->end_time,
                        'notes' => $trip->notes,
                        'start_location' => $trip->locations
                            ->where('pivot.type', 'start_location')
                            ->first(),
                        'delivery_locations' => $trip->locations
                            ->where('pivot.type', 'delivery')
                            ->values(),
                        'created_at' => $trip->created_at,
                        'updated_at' => $trip->updated_at,
                    ];
                });

            return response()->json($trips);
        } catch (\Exception $e) {
            \Log::error('Trip fetch error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch trips',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
