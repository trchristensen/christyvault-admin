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
            Log::info('User attempting to fetch trips:', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);

            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $employee = $user->employee;
            Log::info('Employee check:', [
                'user_id' => $user->id,
                'employee' => $employee ? [
                    'id' => $employee->id,
                    'name' => $employee->name
                ] : 'No employee found'
            ]);

            if (!$employee) {
                return response()->json(['error' => 'User is not associated with an employee record'], 403);
            }

            $driver = $employee->driver;
            Log::info('Driver check:', [
                'employee_id' => $employee->id,
                'driver' => $driver ? [
                    'id' => $driver->id,
                    'license_number' => $driver->license_number
                ] : 'No driver found'
            ]);
            if (!$driver) {
                return response()->json(['error' => 'Employee is not associated with a driver record'], 403);
            }

            $trips = Trip::orderBy('scheduled_date', 'desc')
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
            Log::error('Trip fetch error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch trips',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
