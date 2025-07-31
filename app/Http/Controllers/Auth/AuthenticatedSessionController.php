<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();

        $user = Auth::user();


        // Create a Sanctum token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        // Notify admin of new login (only for non-admin users)
        if (!$user->isAdmin()) {
            NotificationService::notifyAdminOfNewLogin($user, [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_time' => now()->toISOString(),
            ]);
        }

        return response()->json([
            'success' => true,
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'avatar_url' => $user->avatar_url,
                'student_verified' => $user->student_verified,
                'has_premium' => $user->has_premium
            ]
        ], 200);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        // For token-based authentication, revoke the current access token
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        // Only handle session-based logout if session is available (for web authentication)
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }
}
