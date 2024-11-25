<?php

use App\Http\Controllers\TripController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;


Route::get('/test', function (Request $request) {
    return response()->json([
        'message' => 'Hello World'
    ]);
});

// Public routes
Route::post('/tokens/create', function (Request $request) {

    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'device_name' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();



    // if (! $user || ! Hash::check($request->password, $user->password)) {
    //     return response()->json([
    //         'message' => 'The provided credentials are incorrect.'
    //     ], 401);
    // }



    return response()->json([
        'token' => $user->createToken($request->device_name)->plainTextToken
    ]);
});

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user()
        ]);
    });

    Route::get('/trips', [TripController::class, 'index']);
    Route::get('/trips/{trip}', [TripController::class, 'show']); // Add this line

});
