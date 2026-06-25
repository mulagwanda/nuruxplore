<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ExportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project:uuid}', [ProjectController::class, 'show']);
    Route::put('/projects/{project:uuid}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project:uuid}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{project:uuid}/duplicate', [ProjectController::class, 'duplicate']);

    // Research-profile workflow
    Route::post('/projects/{project:uuid}/build-research-profile', [ProjectController::class, 'buildResearchProfile']);
    Route::put('/projects/{project:uuid}/research-profile', [ProjectController::class, 'updateResearchProfile']);
    Route::post('/projects/{project:uuid}/approve-research-profile', [ProjectController::class, 'approveResearchProfile']);
    Route::post('/projects/{project:uuid}/generate-outline', [ProjectController::class, 'generateOutline']);
    Route::post('/projects/{project:uuid}/generate-complete', [ProjectController::class, 'generateComplete']);
    Route::get('/projects/{project:uuid}/generation-status', [ProjectController::class, 'generationStatus']);
    Route::post('/projects/{project:uuid}/assemble-document', [ProjectController::class, 'assembleDocument']);
    Route::post('/projects/{project:uuid}/consistency-check', [ProjectController::class, 'consistencyCheck']);

    Route::get('/projects/{project:uuid}/versions', [App\Http\Controllers\Api\VersionController::class, 'index']);
    Route::post('/projects/{project:uuid}/versions/{version}/restore', [App\Http\Controllers\Api\VersionController::class, 'restore']);

    Route::get('/projects/{project:uuid}/sections', [SectionController::class, 'index']);
    Route::post('/projects/{project:uuid}/sections', [SectionController::class, 'store']);
    Route::get('/sections/{section}', [SectionController::class, 'show']);
    Route::put('/sections/{section}', [SectionController::class, 'update']);
    Route::delete('/sections/{section}', [SectionController::class, 'destroy']);
    Route::post('/sections/reorder', [SectionController::class, 'reorder']);
    Route::post('/sections/{section}/ai-generate', [SectionController::class, 'aiGenerate']);
    Route::post('/sections/{section}/ai-revise', [SectionController::class, 'aiRevise']);

    Route::get('/projects/{project:uuid}/sources', [SourceController::class, 'index']);
    Route::post('/projects/{project:uuid}/sources', [SourceController::class, 'store']);
    Route::post('/sources/upload', [SourceController::class, 'upload']);
    Route::post('/sources/{source}/verify', [SourceController::class, 'verify']);
    Route::delete('/sources/{source}', [SourceController::class, 'destroy']);

    Route::get('/projects/{project:uuid}/messages', [MessageController::class, 'index']);
    Route::post('/projects/{project:uuid}/messages', [MessageController::class, 'store']);

    Route::post('/projects/{project:uuid}/export/pdf', [ExportController::class, 'pdf']);
    Route::post('/projects/{project:uuid}/export/word', [ExportController::class, 'word']);

    Route::get('/credits/balance', function (Request $request) {
        return response()->json([
            'balance' => $request->user()->credits_balance,
            'plan' => $request->user()->subscription_plan,
            'expires_at' => $request->user()->subscription_expires_at,
        ]);
    });
});
