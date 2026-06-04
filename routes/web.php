<?php

use Illuminate\Support\Facades\Route;
use App\Models\NuruxploreProject;

// Landing Page
Route::get('/', function () {
    return view('landing');
});

// Public Pages (no auth required)
Route::get('/pricing', function () {
    return view('pricing');
})->name('pricing');

// Authentication Pages (guests only)
Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');
    
    Route::get('/register', function () {
        return view('auth.register');
    })->name('register');
});

// Authenticated Pages
Route::middleware('auth')->group(function () {
    
    // Dashboard
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    
    // Workspace
    Route::get('/workspace/{project:uuid}', function (NuruxploreProject $project) {
        return view('workspace', ['projectId' => $project->uuid]);
    })->name('workspace');

    // General Chat
    Route::get('/chat/{uuid?}', function ($uuid = null) {
        return view('chat', ['uuid' => $uuid]);
    })->middleware('auth')->name('chat');
});