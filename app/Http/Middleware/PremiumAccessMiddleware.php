<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class PremiumAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        // Debug logging
        Log::info('PremiumAccessMiddleware Debug', [
            'path' => $request->path(),
            'method' => $request->method(),
            'hasUser' => !!$user,
            'userId' => $user?->id,
            'userRole' => $user?->role,
            'hasPremium' => $user?->has_premium,
            'premiumExpiresAt' => $user?->premium_expires_at,
            'hasPremiumAccess' => $user?->hasPremiumAccess(),
            'authorizationHeader' => $request->header('Authorization') ? 'present' : 'missing'
        ]);

        // If not authenticated, let the auth middleware handle it
        if (!$user) {
            Log::warning('PremiumAccessMiddleware: No authenticated user found');
            return $next($request);
        }

        // Only apply to creators
        if (!$user->isCreator()) {
            Log::info('PremiumAccessMiddleware: User is not a creator, allowing access', [
                'role' => $user->role
            ]);
            return $next($request);
        }

        // Allow access to profile, portfolio, payment, and notification pages
        $allowedPaths = [
            '/api/profile',
            '/api/portfolio',
            '/api/payment',
            '/api/notifications',
            '/api/logout',
        ];

        $currentPath = $request->path();
        
        // Check if current path is allowed
        foreach ($allowedPaths as $allowedPath) {
            if (str_starts_with($currentPath, ltrim($allowedPath, '/'))) {
                Log::info('PremiumAccessMiddleware: Path is in allowed list', [
                    'path' => $currentPath,
                    'allowedPath' => $allowedPath
                ]);
                return $next($request);
            }
        }

        // Check if user has premium access
        if (!$user->hasPremiumAccess()) {
            Log::warning('PremiumAccessMiddleware: Creator without premium access blocked', [
                'userId' => $user->id,
                'path' => $currentPath,
                'hasPremium' => $user->has_premium,
                'premiumExpiresAt' => $user->premium_expires_at,
                'hasPremiumAccess' => $user->hasPremiumAccess()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Premium subscription required',
                'error' => 'premium_required',
                'redirect_to' => '/subscription',
                'user' => [
                    'has_premium' => $user->has_premium,
                    'premium_expires_at' => $user->premium_expires_at?->format('Y-m-d H:i:s'),
                ]
            ], 403);
        }

        Log::info('PremiumAccessMiddleware: Creator with premium access allowed', [
            'userId' => $user->id,
            'path' => $currentPath
        ]);

        return $next($request);
    }
} 