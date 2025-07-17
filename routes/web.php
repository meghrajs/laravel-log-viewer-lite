<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\LogTestController;

// Home route (can be changed to your app's real homepage)
Route::get('/', function () {
    return view('welcome');
});

// Log Viewer Interface
Route::get('/log-viewer', [LogViewerController::class, 'index']);
Route::get('/logs', [LogViewerController::class, 'fetch']);
Route::get('/logs/list', [LogViewerController::class, 'list']);
Route::post('/clear-logs', [LogViewerController::class, 'clear']);

// Test Data Generator for Logs
Route::get('/test-log', [LogTestController::class, 'generate']);

// Cache clear utility
Route::get('/clear-cache', function (Request $request) {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');

    return response()->json(['message' => 'Cache cleared successfully!']);
});

// Auth logout shortcut
Route::post('/logout', function () {
    Auth::logout();
    return redirect('/');
})->name('logout');
