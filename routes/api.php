<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // Projects
    Route::apiResource('/projects', ProjectController::class);
    Route::post('/projects/{project}/duplicate', [ProjectController::class, 'duplicate']);
    
    // Placeholder routes for tomorrow
    Route::get('/projects/{project}/sections', function () {
        return response()->json(['message' => 'Coming Day 2']);
    });
    
    Route::get('/projects/{project}/messages', function () {
        return response()->json(['message' => 'Coming Day 2']);
    });
    
    Route::get('/credits/balance', function (Request $request) {
        return response()->json([
            'balance' => $request->user()->credits_balance,
            'plan' => $request->user()->subscription_plan,
        ]);
    });
});