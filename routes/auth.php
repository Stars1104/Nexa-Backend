<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\PagarMeAuthController;
use Illuminate\Support\Facades\Route;

// API Registration and Login routes (stateless)
Route::post('/register', [RegisteredUserController::class, 'store'])
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->name('login');

// Password Reset routes (stateless)
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->name('password.email');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->name('password.store');

// Password Update route (requires authentication)
Route::put('/update-password', [NewPasswordController::class, 'update'])
    ->middleware('auth:sanctum')
    ->name('password.update');

// Email Verification routes
Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, 'verifyFromLink'])
    ->name('verification.verify');

Route::get('/verify-email', [VerifyEmailController::class, 'verify'])
    ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
    ->name('verification.verify.authenticated');

Route::post('/resend-verification', [VerifyEmailController::class, 'resend'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.resend');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:6,1']) 
    ->name('verification.send');

// Logout route (requires authentication)
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('logout');

// Google OAuth routes
Route::get('/google/redirect', [GoogleController::class, 'redirectToGoogle'])
    ->name('google.redirect');

Route::get('/google/callback', [GoogleController::class, 'handleGoogleCallback'])
    ->name('google.callback');

// Pagar.me Authentication routes
Route::post('/pagarme/authenticate', [PagarMeAuthController::class, 'authenticate'])
    ->name('pagarme.authenticate');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/pagarme/link-account', [PagarMeAuthController::class, 'linkAccount'])
        ->name('pagarme.link-account');
    
    Route::post('/pagarme/unlink-account', [PagarMeAuthController::class, 'unlinkAccount'])
        ->name('pagarme.unlink-account');
    
    Route::get('/pagarme/account-info', [PagarMeAuthController::class, 'getAccountInfo'])
        ->name('pagarme.account-info');
});

Route::post('/google/auth', [GoogleController::class, 'handleGoogleWithRole'])
    ->name('google.auth');
