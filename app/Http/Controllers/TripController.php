<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index(Request $request)
    {
        $driver_id = $request->user()->driver->id;
        $trips = Trip::where('driver_id', $driver_id)
            ->orderBy('created_at', 'desc')
            ->with('locations')
            ->get();

        return response()->json($trips);
    }
}
