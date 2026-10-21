<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchScheduleController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Public\CatalogController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffScheduleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'time' => now()->toIso8601String(),
    ]));

    Route::post('/auth/register', [RegisterController::class, 'store']);
    Route::post('/auth/login', [SessionController::class, 'store']);
    Route::post('/auth/logout', [SessionController::class, 'destroy']);
    Route::post('/invitations/accept', [InvitationController::class, 'accept']);

    Route::middleware(['auth:sanctum', 'tenant.auth'])->group(function () {
        Route::get('/auth/me', [SessionController::class, 'show']);
    });

    Route::prefix('public/{slug}')->middleware('tenant.public')->group(function () {
        Route::get('/branches', [CatalogController::class, 'branches']);
        Route::get('/services', [CatalogController::class, 'services']);
        Route::get('/staff', [CatalogController::class, 'staff']);
        Route::get('/availability', AvailabilityController::class);
        Route::post('/appointments', [AppointmentController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', 'tenant.auth'])->group(function () {
        Route::apiResource('branches', BranchController::class);
        Route::put('branches/{branch}/opening-hours', [BranchScheduleController::class, 'replaceOpeningHours']);
        Route::post('branches/{branch}/holidays', [BranchScheduleController::class, 'storeHoliday']);
        Route::apiResource('service-categories', ServiceCategoryController::class)
            ->parameters(['service-categories' => 'serviceCategory']);
        Route::apiResource('services', ServiceController::class);
        Route::apiResource('staff', StaffController::class)->parameters(['staff' => 'staff']);
        Route::put('staff/{staff}/working-hours', [StaffScheduleController::class, 'replaceWorkingHours']);
        Route::post('staff/{staff}/exceptions', [StaffScheduleController::class, 'storeException']);
        Route::post('invitations', [InvitationController::class, 'store']);
        Route::get('availability', AvailabilityController::class);
        Route::get('appointments', [AppointmentController::class, 'index']);
        Route::post('appointments', [AppointmentController::class, 'store']);
        Route::patch('appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::post('appointments/{appointment}/status', [AppointmentController::class, 'status']);
    });
});
