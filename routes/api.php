<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ExportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // Projects
    Route::apiResource('/projects', ProjectController::class);
    Route::post('/projects/{project}/duplicate', [ProjectController::class, 'duplicate']);
    Route::post('/projects/{project}/generate-outline', [ProjectController::class, 'generateOutline']);
    
    // Sections
    Route::get('/projects/{project}/sections', [SectionController::class, 'index']);
    Route::post('/projects/{project}/sections', [SectionController::class, 'store']);
    Route::get('/sections/{section}', [SectionController::class, 'show']);
    Route::put('/sections/{section}', [SectionController::class, 'update']);
    Route::delete('/sections/{section}', [SectionController::class, 'destroy']);
    Route::post('/sections/reorder', [SectionController::class, 'reorder']);
    Route::post('/sections/{section}/ai-generate', [SectionController::class, 'aiGenerate']);
    Route::post('/sections/{section}/ai-revise', [SectionController::class, 'aiRevise']);
    
    // Sources
    Route::get('/projects/{project}/sources', [SourceController::class, 'index']);
    Route::post('/projects/{project}/sources', [SourceController::class, 'store']);
    Route::post('/sources/upload', [SourceController::class, 'upload']);
    Route::post('/sources/{source}/verify', [SourceController::class, 'verify']);
    Route::delete('/sources/{source}', [SourceController::class, 'destroy']);
    
    // Messages (AI Chat)
    Route::get('/projects/{project}/messages', [MessageController::class, 'index']);
    Route::post('/projects/{project}/messages', [MessageController::class, 'store']);
    
    // Export
    Route::post('/projects/{project}/export/pdf', [ExportController::class, 'pdf']);
    Route::post('/projects/{project}/export/word', [ExportController::class, 'word']);
    
    // Credits
    Route::get('/credits/balance', function (Request $request) {
        return response()->json([
            'balance' => $request->user()->credits_balance,
            'plan' => $request->user()->subscription_plan,
            'expires_at' => $request->user()->subscription_expires_at,
        ]);
    });
});