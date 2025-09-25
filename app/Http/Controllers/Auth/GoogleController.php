<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class GoogleController extends Controller
{


    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle(): JsonResponse
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        
        return response()->json([
            'success' => true,
            'redirect_url' => $url
        ]);
    }

    /**
     * Obtain the user information from Google.
     */
    public function handleGoogleCallback(Request $request): JsonResponse
    {
        try {
            // Log the incoming request for debugging
            \Log::info('Google OAuth callback received', [
                'query_params' => $request->query(),
                'has_code' => $request->has('code'),
                'has_role' => $request->has('role'),
                'role' => $request->input('role'),
                'has_is_student' => $request->has('is_student'),
                'is_student' => $request->input('is_student'),
            ]);
            
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            // Get role from request or default to creator
            $role = $request->input('role', 'creator');
            $isStudent = $request->boolean('is_student', false);
            
            // Validate role
            if (!in_array($role, ['creator', 'brand', 'student'])) {
                $role = 'creator'; // Default to creator if invalid role
            }
            
            // If user wants to be a student, set role to 'student'
            if ($isStudent) {
                $role = 'student';
            }
            
            // Check if user already exists by Google ID or email
            $user = User::where('google_id', $googleUser->getId())
                       ->orWhere('email', $googleUser->getEmail())
                       ->first();
            
            if ($user) {
                // Prepare update data
                $updateData = [];
                
                // Update Google ID if not set
                if (!$user->google_id) {
                    $updateData = array_merge($updateData, [
                        'google_id' => $googleUser->getId(),
                        'google_token' => $googleUser->token,
                        'google_refresh_token' => $googleUser->refreshToken,
                        'avatar_url' => $googleUser->getAvatar() ?: $user->avatar_url,
                    ]);
                }
                
                // Update role if provided and different
                if ($role && $user->role !== $role) {
                    $updateData['role'] = $role;
                }
                
                // If user wants to be a student, update role and give trial access
                // They need to complete the education history form for full verification
                if ($isStudent && !$user->student_verified) {
                    $updateData['free_trial_expires_at'] = now()->addMonth(); // Grant 1 month free trial
                    // Don't set student_verified = true yet - they need to complete the form first
                }
                
                // Apply updates if any
                if (!empty($updateData)) {
                    $user->update($updateData);
                }
                
                $token = $user->createToken('auth_token')->plainTextToken;
                
                \Log::info('Google OAuth login successful', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                ]);
                
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
                        'has_premium' => $user->has_premium
                    ],
                    'message' => 'Login successful'
                ], 200);
            } else {
                // Prepare user data
                $userData = [
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'password' => Hash::make('12345678'), // Default password for OAuth users
                    'role' => $role, // Use the provided role
                    'avatar_url' => $googleUser->getAvatar(),
                    'google_id' => $googleUser->getId(),
                    'google_token' => $googleUser->token,
                    'google_refresh_token' => $googleUser->refreshToken,
                    'email_verified_at' => now(), // Google users are pre-verified
                    'birth_date' => '1990-01-01', // Default birth date for OAuth users
                    'gender' => 'other', // Default gender
                ];
                
                // If user is a student, set role to student and give trial access
                // They need to complete the education history form for full verification
                if ($isStudent) {
                    $userData['free_trial_expires_at'] = now()->addMonth(); // Grant 1 month free trial
                    // Don't set student_verified = true yet - they need to complete the form first
                }
                
                // Create new user with the specified role
                $user = User::create($userData);
                
                // Notify admin of new user registration
                \App\Services\NotificationService::notifyAdminOfNewRegistration($user);
                
                $token = $user->createToken('auth_token')->plainTextToken;
                
                \Log::info('Google OAuth registration successful', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'selected_role' => $role,
                ]);
                
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
                        'has_premium' => $user->has_premium
                    ],
                    'message' => 'Registration successful'
                ], 201);
            }
            
        } catch (\Exception $e) {
            \Log::error('Google OAuth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query_params' => $request->query(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Handle Google OAuth with role selection
     */
    public function handleGoogleWithRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:creator,brand'
        ]);

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            // Check if user already exists by Google ID or email
            $user = User::where('google_id', $googleUser->getId())
                       ->orWhere('email', $googleUser->getEmail())
                       ->first();
            
            if ($user) {
                // Update Google ID if not set and update role if different
                $updateData = ['role' => $request->role];
                
                if (!$user->google_id) {
                    $updateData = array_merge($updateData, [
                        'google_id' => $googleUser->getId(),
                        'google_token' => $googleUser->token,
                        'google_refresh_token' => $googleUser->refreshToken,
                        'avatar_url' => $googleUser->getAvatar() ?: $user->avatar_url,
                    ]);
                }
                
                $user->update($updateData);
                
                $token = $user->createToken('auth_token')->plainTextToken;
                
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
                        'has_premium' => $user->has_premium
                    ],
                    'message' => 'Login successful'
                ], 200);
            } else {
                // Create new user with specified role
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'password' => Hash::make('12345678'), // Default password for OAuth users
                    'role' => $request->role,
                    'avatar_url' => $googleUser->getAvatar(),
                    'google_id' => $googleUser->getId(),
                    'google_token' => $googleUser->token,
                    'google_refresh_token' => $googleUser->refreshToken,
                    'email_verified_at' => now(),
                ]);
                
                // Notify admin of new user registration
                \App\Services\NotificationService::notifyAdminOfNewRegistration($user);
                
                $token = $user->createToken('auth_token')->plainTextToken;
                
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
                        'has_premium' => $user->has_premium
                    ],
                    'message' => 'Registration successful'
                ], 201);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed: ' . $e->getMessage()
            ], 422);
        }
    }


} 