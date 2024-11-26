<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverController extends Controller
{
    public function getChristyVaultLocation(Request $request)
    {
        try {
            $driver = $request->user()->employee;

            if (!$driver) {
                return response()->json([
                    'error' => 'Driver not found'
                ], 404);
            }

            dd($driver->toJson());

            $location = Location::getChristyVaultByName($driver->christy_location);

            if (!$location) {
                return response()->json([
                    'error' => 'Christy Vault location not found'
                ], 404);
            }

            return response()->json([
                'id' => $location->id,
                'name' => $location->name,
                'latitude' => (float)$location->latitude,
                'longitude' => (float)$location->longitude,
                'radius_feet' => $location->radius_feet
            ]);
        } catch (\Exception $e) {
            Log::error('Location fetch error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to fetch location'
            ], 500);
        }
    }
}
