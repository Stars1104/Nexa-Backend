<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the rate limiting configuration for different
    | parts of the application. These settings help prevent abuse while
    | maintaining good user experience for legitimate users.
    |
    */

    'auth' => [
        'login' => [
            'attempts' => 30,        // Increased from 20 to 30 attempts per minute per IP
            'decay_minutes' => 1,    // Time window in minutes
            'lockout_minutes' => 3,  // Reduced lockout from 5 to 3 minutes
        ],
        'registration' => [
            'attempts' => 15,        // Increased from 10 to 15 attempts per minute per IP
            'decay_minutes' => 1,    // Time window in minutes
            'lockout_minutes' => 5,  // Reduced lockout from 10 to 5 minutes
        ],
        'password_reset' => [
            'attempts' => 10,        // Increased from 5 to 10 attempts per minute per IP
            'decay_minutes' => 1,    // Time window in minutes
            'lockout_minutes' => 10, // Reduced lockout from 15 to 10 minutes
        ],
    ],

    'api' => [
        'general' => [
            'attempts' => 1000,      // General API requests per minute per user/IP
            'decay_minutes' => 1,    // Time window in minutes
        ],
        'notifications' => [
            'attempts' => 600,       // Increased from 300 to 600 requests per minute per user
            'decay_minutes' => 1,    // Time window in minutes
        ],
        'user_status' => [
            'attempts' => 600,       // User status checks per minute per user
            'decay_minutes' => 1,    // Time window in minutes
        ],
        'payment' => [
            'attempts' => 100,       // Payment requests per minute per user
            'decay_minutes' => 1,    // Time window in minutes
        ],
    ],

    'email_verification' => [
        'resend' => [
            'attempts' => 6,         // Resend verification email attempts per hour per user
            'decay_minutes' => 60,   // Time window in minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Response Messages
    |--------------------------------------------------------------------------
    |
    | Custom messages for different rate limiting scenarios
    |
    */

    'messages' => [
        'auth' => [
            'login' => 'Muitas tentativas de login. Tente novamente em alguns instantes.',
            'registration' => 'Muitas tentativas de registro. Tente novamente em alguns instantes.',
            'password_reset' => 'Muitas tentativas de redefinição de senha. Tente novamente em alguns instantes.',
        ],
        'general' => 'Muitas requisições. Tente novamente em alguns instantes.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Headers
    |--------------------------------------------------------------------------
    |
    | Whether to include rate limiting headers in responses
    |
    */

    'include_headers' => true,

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Storage
    |--------------------------------------------------------------------------
    |
    | The cache store to use for rate limiting
    |
    */

    'cache_store' => env('RATE_LIMITING_CACHE_STORE', 'default'),
]; 