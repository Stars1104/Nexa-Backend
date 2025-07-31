<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PagarMeAuthController extends Controller
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.pagarme.api_key') ?? '';
        $this->baseUrl = config('services.pagarme.environment') === 'production' 
            ? 'https://api.pagar.me/core/v5' 
            : 'https://api.pagar.me/core/v5';
    }

    /**
     * Authenticate user using Pagar.me account_id
     */
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => 'required|string|max:255',
            'email' => 'required|email',
            'name' => 'required|string|max:255',
        ]);

        try {
            // Check if Pagar.me is configured
            if (empty($this->apiKey)) {
                return response()->json([
                    'message' => 'Pagar.me not configured. Please contact support.',
                ], 503);
            }

            // Verify account_id with Pagar.me API
            $accountVerification = $this->verifyAccountWithPagarMe($request->account_id);
            
            if (!$accountVerification['success']) {
                return response()->json([
                    'message' => 'Invalid account_id. Please check your credentials.',
                ], 401);
            }

            // Find or create user
            $user = User::where('account_id', $request->account_id)
                       ->orWhere('email', $request->email)
                       ->first();

            if (!$user) {
                // Create new user with account_id
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'account_id' => $request->account_id,
                    'password' => Hash::make(Str::random(32)), // Generate random password
                    'role' => 'creator', // Default role for pagar.me users
                    'email_verified_at' => now(), // Auto-verify email for pagar.me users
                ]);

                Log::info('New user created via Pagar.me authentication', [
                    'user_id' => $user->id,
                    'account_id' => $request->account_id,
                    'email' => $request->email
                ]);
            } else {
                // Update existing user with account_id if not set
                if (!$user->account_id) {
                    $user->update(['account_id' => $request->account_id]);
                }

                Log::info('User authenticated via Pagar.me', [
                    'user_id' => $user->id,
                    'account_id' => $request->account_id,
                    'email' => $request->email
                ]);
            }

            // Create Sanctum token
            $token = $user->createToken('pagarme_auth_token')->plainTextToken;

            // Notify admin of new login (only for non-admin users)
            if (!$user->isAdmin()) {
                NotificationService::notifyAdminOfNewLogin($user, [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'login_time' => now()->toISOString(),
                    'auth_method' => 'pagarme_account_id',
                ]);
            }

            return response()->json([
                'success' => true,
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar_url' => $user->avatar_url,
                    'student_verified' => $user->student_verified,
                    'has_premium' => $user->has_premium,
                    'account_id' => $user->account_id,
                ],
                'message' => 'Authentication successful'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Pagar.me authentication error', [
                'account_id' => $request->account_id,
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Authentication failed. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Link existing user account with Pagar.me account_id
     */
    public function linkAccount(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => 'required|string|max:255',
        ]);

        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if account_id is already in use
            $existingUser = User::where('account_id', $request->account_id)->first();
            if ($existingUser && $existingUser->id !== $user->id) {
                return response()->json([
                    'message' => 'This account_id is already linked to another user.',
                ], 409);
            }

            // Verify account_id with Pagar.me API
            $accountVerification = $this->verifyAccountWithPagarMe($request->account_id);
            
            if (!$accountVerification['success']) {
                return response()->json([
                    'message' => 'Invalid account_id. Please check your credentials.',
                ], 401);
            }

            // Update user with account_id
            $user->update(['account_id' => $request->account_id]);

            Log::info('User account linked with Pagar.me account_id', [
                'user_id' => $user->id,
                'account_id' => $request->account_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account successfully linked with Pagar.me',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'account_id' => $user->account_id,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Pagar.me account linking error', [
                'user_id' => auth()->id(),
                'account_id' => $request->account_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to link account. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unlink Pagar.me account_id from user account
     */
    public function unlinkAccount(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'User not authenticated',
                ], 401);
            }

            if (!$user->account_id) {
                return response()->json([
                    'message' => 'No Pagar.me account linked to this user.',
                ], 400);
            }

            $accountId = $user->account_id;
            $user->update(['account_id' => null]);

            Log::info('User account unlinked from Pagar.me account_id', [
                'user_id' => $user->id,
                'account_id' => $accountId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account successfully unlinked from Pagar.me',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'account_id' => null,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Pagar.me account unlinking error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to unlink account. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Pagar.me account information
     */
    public function getAccountInfo(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'User not authenticated',
                ], 401);
            }

            if (!$user->account_id) {
                return response()->json([
                    'message' => 'No Pagar.me account linked to this user.',
                ], 400);
            }

            // Get account information from Pagar.me
            $accountInfo = $this->getAccountInfoFromPagarMe($user->account_id);
            
            if (!$accountInfo['success']) {
                return response()->json([
                    'message' => 'Failed to retrieve account information.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'account_info' => $accountInfo['data'],
                'linked_at' => $user->updated_at,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Pagar.me account info retrieval error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve account information.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify account_id with Pagar.me API
     */
    private function verifyAccountWithPagarMe(string $accountId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/accounts/' . $accountId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Pagar.me account verification error', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get account information from Pagar.me API
     */
    private function getAccountInfoFromPagarMe(string $accountId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/accounts/' . $accountId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Pagar.me account info retrieval error', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} 