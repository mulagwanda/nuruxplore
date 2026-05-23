<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ExportController;
use App\Models\NuruxploreProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web'])->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // Projects - use explicit UUID binding
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project:uuid}', [ProjectController::class, 'show']);
    Route::put('/projects/{project:uuid}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project:uuid}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{project:uuid}/duplicate', [ProjectController::class, 'duplicate']);
    Route::post('/projects/{project:uuid}/generate-outline', [ProjectController::class, 'generateOutline']);
    Route::post('/projects/{project:uuid}/generate-complete', [ProjectController::class, 'generateComplete']);

    // Versions
    Route::get('/projects/{project:uuid}/versions', [App\Http\Controllers\Api\VersionController::class, 'index']);
    Route::post('/projects/{project:uuid}/versions/{version}/restore', [App\Http\Controllers\Api\VersionController::class, 'restore']);
        
    // Sections - project by UUID, section by ID (since sections don't have UUIDs)
    Route::get('/projects/{project:uuid}/sections', [SectionController::class, 'index']);
    Route::post('/projects/{project:uuid}/sections', [SectionController::class, 'store']);
    Route::get('/sections/{section}', [SectionController::class, 'show']);
    Route::put('/sections/{section}', [SectionController::class, 'update']);
    Route::delete('/sections/{section}', [SectionController::class, 'destroy']);
    Route::post('/sections/reorder', [SectionController::class, 'reorder']);
    Route::post('/sections/{section}/ai-generate', [SectionController::class, 'aiGenerate']);
    Route::post('/sections/{section}/ai-revise', [SectionController::class, 'aiRevise']);
    
    // Sources - project by UUID
    Route::get('/projects/{project:uuid}/sources', [SourceController::class, 'index']);
    Route::post('/projects/{project:uuid}/sources', [SourceController::class, 'store']);
    Route::post('/sources/upload', [SourceController::class, 'upload']);
    Route::post('/sources/{source}/verify', [SourceController::class, 'verify']);
    Route::delete('/sources/{source}', [SourceController::class, 'destroy']);
    
    // Messages - project by UUID
    Route::get('/projects/{project:uuid}/messages', [MessageController::class, 'index']);
    Route::post('/projects/{project:uuid}/messages', [MessageController::class, 'store']);
    
    // Export - project by UUID
    Route::post('/projects/{project:uuid}/export/pdf', [ExportController::class, 'pdf']);
    Route::post('/projects/{project:uuid}/export/word', [ExportController::class, 'word']);
    
    // Credits
    Route::get('/credits/balance', function (Request $request) {
        return response()->json([
            'balance' => $request->user()->credits_balance,
            'plan' => $request->user()->subscription_plan,
            'expires_at' => $request->user()->subscription_expires_at,
        ]);
    });
});