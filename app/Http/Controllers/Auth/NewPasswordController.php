<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['status' => __($status)]);
    }

    /**
     * Handle password update for a specific user.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required', 
                'min:8',
                'different:current_password',
                function ($attribute, $value, $fail) {
                    // Check for at least 1 number
                    if (preg_match_all('/[0-9]/', $value) < 1) {
                        $fail('The password must contain at least 1 number.');
                    }
                    
                    // Check for at least 1 uppercase letter
                    if (preg_match_all('/[A-Z]/', $value) < 1) {
                        $fail('The password must contain at least 1 uppercase letter.');
                    }
                    
                    // Check for at least 1 special character
                    if (preg_match_all('/[^a-zA-Z0-9]/', $value) < 1) {
                        $fail('The password must contain at least 1 special character.');
                    }
                    
                    // Check for at least 1 lowercase letter
                    if (preg_match_all('/[a-z]/', $value) < 1) {
                        $fail('The password must contain at least 1 lowercase letter.');
                    }
                }
            ],
        ]);

        try {
            $user = User::findOrFail($request->user_id);
            
            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }
            
            $user->forceFill([
                'password' => Hash::make($request->new_password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update password: ' . $e->getMessage()
            ], 500);
        }
    }
}