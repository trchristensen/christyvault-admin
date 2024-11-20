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

            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $employee = $user->employee;

            if (!$employee) {
                return response()->json(['error' => 'User is not associated with an employee record'], 403);
            }

            $driver = $employee->driver;

            if (!$driver) {
                return response()->json(['error' => 'Employee is not associated with a driver record'], 403);
            }

            $trips = Trip::where('driver_id', $driver->id)  // Add this line
                ->orderBy('scheduled_date', 'desc')
                ->with('driver')
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
                        'driver' => $trip->driver ? [
                            'id' => $trip->driver->id,
                            'name' => $trip->driver->name,
                            'email' => $trip->driver->email,
                            'position' => $trip->driver->position
                        ] : null,
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
            Log::error('Trip fetch error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch trips',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
