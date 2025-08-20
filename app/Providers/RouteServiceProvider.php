<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Rate limiting for new user flow (registration + immediate login)
        RateLimiter::for('new-user-flow', function (Request $request) {
            return Limit::perMinute(25)->by($request->ip())->response(function () use ($request) {
                // Log rate limiting for debugging
                \Log::info('New user flow rate limited', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'attempts_allowed' => 25,
                    'lockout_minutes' => 5
                ]);
                
                return response()->json([
                    'message' => 'Muitas tentativas de criação de conta. Tente novamente em alguns instantes.',
                    'retry_after' => 300, // 5 minutes
                    'error_type' => 'new_user_flow_rate_limited'
                ], 429);
            });
        });

        // Rate limiting for authentication endpoints (more lenient for new users)
        RateLimiter::for('auth', function (Request $request) {
            $config = config('rate_limiting.auth.login');
            return Limit::perMinute($config['attempts'])
                ->by($request->ip())
                ->response(function () use ($config, $request) {
                    // Log rate limiting for debugging
                    \Log::info('Route-level auth rate limited', [
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'attempts_allowed' => $config['attempts'],
                        'lockout_minutes' => $config['lockout_minutes']
                    ]);
                    
                    return response()->json([
                        'message' => config('rate_limiting.messages.auth.login'),
                        'retry_after' => $config['lockout_minutes'] * 60,
                        'error_type' => 'auth_rate_limited'
                    ], 429);
                });
        });

        // Rate limiting for registration (prevent spam but allow legitimate users)
        RateLimiter::for('registration', function (Request $request) {
            $config = config('rate_limiting.auth.registration');
            return Limit::perMinute($config['attempts'])
                ->by($request->ip())
                ->response(function () use ($config, $request) {
                    // Log rate limiting for debugging
                    \Log::info('Route-level registration rate limited', [
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'attempts_allowed' => $config['attempts'],
                        'lockout_minutes' => $config['lockout_minutes']
                    ]);
                    
                    return response()->json([
                        'message' => config('rate_limiting.messages.auth.registration'),
                        'retry_after' => $config['lockout_minutes'] * 60,
                        'error_type' => 'registration_rate_limited'
                    ], 429);
                });
        });

        // Rate limiting for password reset
        RateLimiter::for('password-reset', function (Request $request) {
            $config = config('rate_limiting.auth.password_reset');
            return Limit::perMinute($config['attempts'])
                ->by($request->ip())
                ->response(function () use ($config) {
                    return response()->json([
                        'message' => config('rate_limiting.messages.auth.password_reset'),
                        'retry_after' => $config['lockout_minutes'] * 60,
                        'error_type' => 'password_reset_rate_limited'
                    ], 429);
                });
        });

        // General API rate limiting
        RateLimiter::for('api', function (Request $request) {
            $config = config('rate_limiting.api.general');
            return Limit::perMinute($config['attempts'])
                ->by($request->user()?->id ?: $request->ip());
        });

        // Add specific rate limiting for notification endpoints
        RateLimiter::for('notifications', function (Request $request) {
            $config = config('rate_limiting.api.notifications');
            return Limit::perMinute($config['attempts'])
                ->by($request->user()?->id ?: $request->ip());
        });

        // Add specific rate limiting for user status checks
        RateLimiter::for('user-status', function (Request $request) {
            $config = config('rate_limiting.api.user_status');
            return Limit::perMinute($config['attempts'])
                ->by($request->user()?->id ?: $request->ip());
        });

        // Add specific rate limiting for payment endpoints
        RateLimiter::for('payment', function (Request $request) {
            $config = config('rate_limiting.api.payment');
            return Limit::perMinute($config['attempts'])
                ->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
