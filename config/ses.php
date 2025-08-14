<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AWS SES Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for AWS SES (Simple Email Service).
    | Make sure to set the following environment variables:
    | - AWS_ACCESS_KEY_ID
    | - AWS_SECRET_ACCESS_KEY
    | - AWS_DEFAULT_REGION
    | - AWS_SES_REGION (optional, defaults to AWS_DEFAULT_REGION)
    |
    */

    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_SES_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
    
    'version' => 'latest',
    
    'credentials' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SES Configuration
    |--------------------------------------------------------------------------
    |
    | Additional SES-specific configuration options.
    |
    */

    'ses' => [
        'region' => env('AWS_SES_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        'version' => 'latest',
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Configuration
    |--------------------------------------------------------------------------
    |
    | Default email settings for the application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@nexa.com'),
        'name' => env('MAIL_FROM_NAME', 'Nexa Platform'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Settings
    |--------------------------------------------------------------------------
    |
    | Email verification configuration.
    |
    */

    'verification' => [
        'expire' => env('EMAIL_VERIFICATION_EXPIRE', 60), // minutes
        'resend_throttle' => env('EMAIL_VERIFICATION_RESEND_THROTTLE', '6,1'), // attempts, minutes
    ],
]; 