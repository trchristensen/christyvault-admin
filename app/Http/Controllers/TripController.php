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

            Log::info('User and relationships:', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'has_employee' => $user->employee ? true : false,
                'employee_id' => $user->employee?->id,
                'has_driver' => $user->employee?->driver ? true : false,
                'driver_id' => $user->employee?->driver?->id
            ]);

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

            Log::info('Driver check:', [
                'driver_id' => $driver->id,
                'driver' => $driver
            ]);



            $trips = Trip::where('driver_id', $employee->id)  // Add this line
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


    public function show(Request $request, Trip $trip)
    {
        try {
            $user = $request->user();
            $tripId = $trip->id;


            // Add the query to find the trip
            $trip = Trip::where('id', $tripId)
                ->where('driver_id', $user->employee->id)  // Ensure driver can only see their trips
                ->with(['driver', 'locations' => function ($query) {
                    $query->orderBy('locationables.sequence', 'asc');
                }])
                ->first();

            if (!$trip) {
                Log::warning('Trip not found or unauthorized', [
                    'trip_id' => $tripId,
                    'user_id' => $user->id
                ]);
                return response()->json(['error' => 'Trip not found'], 404);
            }

            Log::info('Trip show request:', [
                'trip_id' => $trip->id,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'has_employee' => $user->employee ? true : false,
                'employee_id' => $user->employee?->id,
                'trip_driver_id' => $trip->driver_id
            ]);



            if (!$user->employee) {
                Log::warning('User has no employee record', ['user_id' => $user->id]);
                return response()->json(['error' => 'User is not associated with an employee record'], 403);
            }

            // Check if the trip belongs to this driver
            if ($trip->driver_id !== $user->employee->id) {
                Log::warning('Trip access denied', [
                    'trip_driver_id' => $trip->driver_id,
                    'user_employee_id' => $user->employee->id
                ]);
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $trip->load(['driver', 'locations' => function ($query) {
                $query->orderBy('locationables.sequence', 'asc');
            }]);

            Log::info('Trip data loaded:', [
                'trip_number' => $trip->trip_number,
                'has_driver' => $trip->driver ? true : false,
                'locations_count' => $trip->locations->count()
            ]);

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
            ]);
        } catch (\Exception $e) {
            Log::error('Trip fetch error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'trip_id' => $trip->id ?? null
            ]);
            return response()->json([
                'error' => 'Failed to fetch trip details',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
