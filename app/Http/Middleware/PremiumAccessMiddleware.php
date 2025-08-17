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
                'role' => $user->role,
                'path' => $request->path()
            ]);
            return $next($request);
        }

        // For creators, check if they have premium access for restricted features
        // But allow access to basic chat functionality even without premium
        
        // Get the current path
        $currentPath = $request->path();
        
        // Define paths that require premium for creators
        $premiumRequiredPaths = [
            'api/campaigns', // Campaign applications
            'api/connections', // Connection requests
            'api/direct-chat', // Direct messaging
            'api/portfolio', // Portfolio management
        ];
        
        // Check if current path requires premium
        $requiresPremium = false;
        foreach ($premiumRequiredPaths as $premiumPath) {
            if (str_starts_with($currentPath, $premiumPath)) {
                $requiresPremium = true;
                break;
            }
        }
        
        // If path doesn't require premium, allow access
        if (!$requiresPremium) {
            Log::info('PremiumAccessMiddleware: Path does not require premium, allowing access', [
                'path' => $currentPath,
                'userId' => $user->id
            ]);
            return $next($request);
        }
        
        // Check if user has premium access for premium-required features
        if (!$user->hasPremiumAccess()) {
            Log::warning('PremiumAccessMiddleware: Creator without premium access blocked from premium feature', [
                'userId' => $user->id,
                'path' => $currentPath,
                'hasPremium' => $user->has_premium,
                'premiumExpiresAt' => $user->premium_expires_at,
                'hasPremiumAccess' => $user->hasPremiumAccess()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Premium subscription required for this feature',
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