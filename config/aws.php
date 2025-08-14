<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AWS Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for AWS services.
    | Make sure to set the following environment variables:
    | - AWS_ACCESS_KEY_ID
    | - AWS_SECRET_ACCESS_KEY
    | - AWS_DEFAULT_REGION
    | - AWS_SES_REGION (optional, defaults to AWS_DEFAULT_REGION)
    |
    */

    'credentials' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'version' => 'latest',

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
    | Additional AWS Services Configuration
    |--------------------------------------------------------------------------
    |
    | You can add configuration for other AWS services here.
    |
    */

    's3' => [
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'version' => 'latest',
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
    ],

    'cloudfront' => [
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'version' => 'latest',
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
    ],
]; 