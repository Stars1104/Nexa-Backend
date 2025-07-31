<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',
        'register',
        'login',
        'logout',
        'forgot-password',
        'reset-password',
        'verify-email/*',
        'email/verification-notification'
    ];

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        // Always exclude API routes
        if ($request->is('api/*')) {
            return true;
        }

        // Exclude specific auth routes
        if ($request->is('register') || 
            $request->is('login') || 
            $request->is('logout') ||
            $request->is('forgot-password') ||
            $request->is('reset-password') ||
            $request->is('verify-email/*') ||
            $request->is('email/verification-notification')) {
            return true;
        }

        return parent::inExceptArray($request);
    }
}
