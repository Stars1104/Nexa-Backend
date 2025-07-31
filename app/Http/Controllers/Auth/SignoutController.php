<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SignoutController extends Controller
{
    /**
     * Handle an incoming signout request.
     */
    public function __invoke(Request $request): JsonResponse
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
            'message' => 'Signed out successfully'
        ], 200);
    }
}
