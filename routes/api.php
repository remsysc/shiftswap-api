<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BusinessUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OwnershipTransferController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftStaffController;
use App\Http\Controllers\ShiftSwapController;
use App\Http\Controllers\TimeOffRequestController;
use App\Http\Middleware\EnsureUserBelongsToBusiness;
use App\Models\User;
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
    Route::post('/login', LoginController::class);
    Route::post('/logout', LogoutController::class)->middleware('auth:sanctum');

    Route::get('/user', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        $business = $user->currentBusiness();

        return array_merge($user->toArray(), [
            'role' => $business ? $user->roleIn($business) : null,
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'timezone' => $business->timezone,
            ] : null,
        ]);
    })->middleware('auth:sanctum');
});

Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);
Route::post('/ownership-transfers/{token}/accept', [OwnershipTransferController::class, 'accept'])->middleware('auth:sanctum');

Route::prefix('businesses/{business}')
    ->middleware(['auth:sanctum', EnsureUserBelongsToBusiness::class])
    ->group(function (): void {
        Route::get('/dashboard', DashboardController::class);
        Route::post('/ownership-transfer', [OwnershipTransferController::class, 'store']);

        Route::get('/staff', [BusinessUserController::class, 'index']);
        Route::post('/staff', [InvitationController::class, 'store']);
        Route::patch('/staff/{user}', [BusinessUserController::class, 'update']);
        Route::get('/invitations', [InvitationController::class, 'index']);

        Route::get('/locations', [LocationController::class, 'index']);
        Route::post('/locations', [LocationController::class, 'store']);
        Route::get('/locations/{location}', [LocationController::class, 'show']);
        Route::patch('/locations/{location}', [LocationController::class, 'update']);
        Route::delete('/locations/{location}', [LocationController::class, 'destroy']);

        Route::get('/shifts', [ShiftController::class, 'index']);
        Route::post('/shifts', [ShiftController::class, 'store']);
        Route::get('/shifts/{shift}', [ShiftController::class, 'show']);
        Route::patch('/shifts/{shift}', [ShiftController::class, 'update']);
        Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy']);

        Route::post('/shifts/{shift}/staff', [ShiftStaffController::class, 'store']);
        Route::delete('/shifts/{shift}/staff/{user}', [ShiftStaffController::class, 'destroy']);

        Route::get('/shift-swaps', [ShiftSwapController::class, 'index']);
        Route::post('/shift-swaps', [ShiftSwapController::class, 'store']);
        Route::post('/shift-swaps/{swap}/request', [ShiftSwapController::class, 'request']);
        Route::post('/shift-swaps/{swap}/approve', [ShiftSwapController::class, 'approve']);
        Route::post('/shift-swaps/{swap}/reject', [ShiftSwapController::class, 'reject']);
        Route::post('/shift-swaps/{swap}/cancel', [ShiftSwapController::class, 'cancel']);

        Route::get('/time-off-requests', [TimeOffRequestController::class, 'index']);
        Route::post('/time-off-requests', [TimeOffRequestController::class, 'store']);
        Route::get('/time-off-requests/{timeOffRequest}', [TimeOffRequestController::class, 'show']);
        Route::post('/time-off-requests/{timeOffRequest}/approve', [TimeOffRequestController::class, 'approve']);
        Route::post('/time-off-requests/{timeOffRequest}/reject', [TimeOffRequestController::class, 'reject']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->withoutScopedBindings();
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    });
