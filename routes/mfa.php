<?php

declare(strict_types=1);

use Aymakan\Mfa\Http\Controllers\MfaEnrollController;
use Aymakan\Mfa\Http\Controllers\MfaStatusController;
use Aymakan\Mfa\Http\Controllers\MfaVerifyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MFA API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the MfaServiceProvider. They are protected
| by the middleware configured in config/mfa.php (default: api, auth:sanctum).
|
*/

// Get MFA status for the authenticated user
Route::get('/status', MfaStatusController::class)
    ->name('mfa.status');

// Verify MFA code (for login flow)
Route::post('/verify', [MfaVerifyController::class, 'verify'])
    ->name('mfa.verify')
    ->middleware('throttle:mfa');

// Enrollment routes
Route::prefix('enroll')->group(function () {
    // Start enrollment - generates secret and returns QR code URI
    Route::post('/start', [MfaEnrollController::class, 'start'])
        ->name('mfa.enroll.start');

    // Confirm enrollment - verifies OTP and enables MFA
    Route::post('/confirm', [MfaEnrollController::class, 'confirm'])
        ->name('mfa.enroll.confirm')
        ->middleware('throttle:mfa');

    // Cancel enrollment - removes pending credential
    Route::delete('/cancel', [MfaEnrollController::class, 'cancel'])
        ->name('mfa.enroll.cancel');
});

// Disable MFA (requires current OTP for security)
Route::delete('/disable', [MfaVerifyController::class, 'disable'])
    ->name('mfa.disable')
    ->middleware('throttle:mfa');
