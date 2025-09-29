<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\JsonResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user) {
            // Check if user is soft deleted (removed)
            if ($user->trashed()) {
                // Revoke all tokens for the user
                $user->tokens()->delete();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Sua conta foi removida da plataforma. Entre em contato com o suporte para mais informações.',
                ], 403);
            }

            // Check if user is blocked (not email verified)
            if (!$user->email_verified_at) {
                // Revoke all tokens for the user
                $user->tokens()->delete();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Sua conta foi bloqueada. Entre em contato com o suporte para mais informações.',
                ], 403);
            }
        }

        return $next($request);
    }
}