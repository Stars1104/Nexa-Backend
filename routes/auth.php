<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\PagarMeAuthController;
use Illuminate\Support\Facades\Route;

// API Registration and Login routes (stateless) - with specific rate limiting
Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('throttle:new-user-flow')
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:auth')
    ->name('login');

// Password Reset routes (stateless) - with auth rate limiting
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('throttle:password-reset')
    ->name('password.email');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('throttle:password-reset')
    ->name('password.store');

// Password Update route (requires authentication)
Route::put('/update-password', [NewPasswordController::class, 'update'])
    ->middleware('auth:sanctum')
    ->name('password.update');


// Logout route (requires authentication)
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('logout');

// Google OAuth routes
Route::get('/google/redirect', [GoogleController::class, 'redirectToGoogle'])
    ->name('google.redirect');

Route::get('/google/callback', [GoogleController::class, 'handleGoogleCallback'])
    ->name('google.callback');

Route::post('/google/auth', [GoogleController::class, 'handleGoogleWithRole'])
    ->middleware('throttle:auth')
    ->name('google.auth');

// PagarMe Auth routes
Route::post('/pagarme/auth', [PagarMeAuthController::class, 'authenticate'])
    ->middleware('throttle:auth')
    ->name('pagarme.auth');
