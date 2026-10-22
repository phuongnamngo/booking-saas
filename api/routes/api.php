<?php

use App\Http\Controllers\Admin\TenantAdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchScheduleController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Public\AppointmentManageController;
use App\Http\Controllers\Public\CatalogController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffScheduleController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WaitlistController;
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

    Route::post('/stripe/webhook', [StripeWebhookController::class, 'store']);

    Route::post('/auth/register', [RegisterController::class, 'store']);
    Route::post('/auth/login', [SessionController::class, 'store'])->middleware('throttle:login');
    Route::post('/auth/logout', [SessionController::class, 'destroy']);
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:password-reset');
    Route::post('/auth/reset-password', [PasswordResetController::class, 'update']);
    Route::post('/invitations/accept', [InvitationController::class, 'accept']);

    Route::get('/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [SessionController::class, 'show']);
        Route::patch('/auth/profile', [ProfileController::class, 'update']);
        Route::put('/auth/password', [ProfileController::class, 'updatePassword']);
        Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'resend']);
    });

    Route::middleware(['auth:sanctum', 'super.admin'])->prefix('admin')->group(function () {
        Route::get('tenants', [TenantAdminController::class, 'index']);
        Route::patch('tenants/{tenant}', [TenantAdminController::class, 'update']);
        Route::post('tenants/{tenant}/suspend', [TenantAdminController::class, 'suspend']);
        Route::post('tenants/{tenant}/unsuspend', [TenantAdminController::class, 'unsuspend']);
    });

    Route::prefix('public/{slug}')->middleware('tenant.public')->group(function () {
        Route::get('/branches', [CatalogController::class, 'branches']);
        Route::get('/services', [CatalogController::class, 'services']);
        Route::get('/staff', [CatalogController::class, 'staff']);
        Route::get('/availability', AvailabilityController::class);
        Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('throttle:booking');
        Route::get('/appointments/{appointment}', [AppointmentManageController::class, 'show']);
        Route::post('/appointments/{appointment}/cancel', [AppointmentManageController::class, 'cancel']);
        Route::patch('/appointments/{appointment}', [AppointmentManageController::class, 'update']);
        Route::post('/appointments/{appointment}/checkout', [CheckoutController::class, 'store']);
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::post('/waitlist', [WaitlistController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', 'tenant.auth', 'verified'])->group(function () {
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
        Route::post('appointments', [AppointmentController::class, 'store'])->middleware('throttle:booking');
        Route::post('appointments/{appointment}/checkout', [CheckoutController::class, 'store']);
        Route::patch('appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::get('appointments/{appointment}/payments', [AppointmentController::class, 'payments']);
        Route::post('appointments/{appointment}/status', [AppointmentController::class, 'status']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'read']);
        Route::get('reports', [ReportController::class, 'index']);
        Route::post('reports/exports', [ReportExportController::class, 'store']);
        Route::get('reports/exports/{export}', [ReportExportController::class, 'show']);
    });
});
