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

            if (!$driver) {
                return response()->json(['error' => 'Employee is not associated with a driver record'], 403);
            }

            $trips = Trip::orderBy('scheduled_date', 'desc')
                ->with('driver')
                ->get();

            // Debug the first trip
            $firstTrip = $trips->first();
            Log::info('First trip details:', [
                'trip_id' => $firstTrip->id,
                'driver_id' => $firstTrip->driver_id,
                'raw_driver' => $firstTrip->driver,
                'driver_relation_loaded' => $firstTrip->relationLoaded('driver'),
                'all_relations_loaded' => $firstTrip->getRelations()
            ]);

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
