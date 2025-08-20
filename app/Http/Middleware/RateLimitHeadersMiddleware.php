<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add rate limiting headers if enabled in config
        if (config('rate_limiting.include_headers', true)) {
            $this->addRateLimitHeaders($request, $response);
        }

        return $response;
    }

    /**
     * Add rate limiting headers to the response
     */
    private function addRateLimitHeaders(Request $request, Response $response): void
    {
        // Get the current route's rate limiter
        $route = $request->route();
        if (!$route) {
            return;
        }

        $middleware = $route->gatherMiddleware();
        $throttleMiddleware = collect($middleware)->first(function ($middleware) {
            return str_starts_with($middleware, 'throttle:');
        });

        if (!$throttleMiddleware) {
            return;
        }

        // Extract the throttle name
        $throttleName = str_replace('throttle:', '', $throttleMiddleware);
        
        // Get rate limiting info
        $key = $this->getThrottleKey($request, $throttleName);
        if ($key) {
            $remaining = RateLimiter::remaining($key, $this->getMaxAttempts($throttleName));
            $retryAfter = RateLimiter::availableIn($key);
            
            if ($remaining !== null) {
                $response->headers->set('X-RateLimit-Limit', $this->getMaxAttempts($throttleName));
                $response->headers->set('X-RateLimit-Remaining', max(0, $remaining));
                
                if ($retryAfter > 0) {
                    $response->headers->set('X-RateLimit-Reset', time() + $retryAfter);
                    $response->headers->set('Retry-After', $retryAfter);
                }
            }
        }
    }

    /**
     * Get the throttle key for the request
     */
    private function getThrottleKey(Request $request, string $throttleName): ?string
    {
        switch ($throttleName) {
            case 'auth':
            case 'registration':
            case 'password-reset':
                return 'throttle:' . $throttleName . ':' . $request->ip();
            case 'api':
                return 'throttle:api:' . ($request->user()?->id ?: $request->ip());
            case 'notifications':
                return 'throttle:notifications:' . ($request->user()?->id ?: $request->ip());
            case 'user-status':
                return 'throttle:user-status:' . ($request->user()?->id ?: $request->ip());
            case 'payment':
                return 'throttle:payment:' . ($request->user()?->id ?: $request->ip());
            default:
                return null;
        }
    }

    /**
     * Get the maximum attempts for a throttle
     */
    private function getMaxAttempts(string $throttleName): int
    {
        switch ($throttleName) {
            case 'auth':
                return config('rate_limiting.auth.login.attempts', 20);
            case 'registration':
                return config('rate_limiting.auth.registration.attempts', 10);
            case 'password-reset':
                return config('rate_limiting.auth.password_reset.attempts', 5);
            case 'api':
                return config('rate_limiting.api.general.attempts', 1000);
            case 'notifications':
                return config('rate_limiting.api.notifications.attempts', 300);
            case 'user-status':
                return config('rate_limiting.api.user_status.attempts', 600);
            case 'payment':
                return config('rate_limiting.api.payment.attempts', 100);
            default:
                return 60;
        }
    }
} 