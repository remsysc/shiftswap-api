<?php

use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are automatically prefixed with "/api" (see bootstrap/app.php).
| All requests and responses use JSON. Protected endpoints authenticate with
| a Bearer token via the "auth:sanctum" guard (see docs/SPEC.md section 5).
|
*/

Route::prefix('auth')->group(function (): void {
    Route::post('/register', RegisterController::class);

    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');
});
