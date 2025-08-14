<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class VerifyEmailController extends Controller
{
    // Resend verification email
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    // Handle email verification via link (for unauthenticated users)
    public function verifyFromLink(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);
        $signature = $request->query('signature');
        $expires = $request->query('expires');

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        // Check if link has expired
        if (time() > $expires) {
            return response()->json(['message' => 'Verification link has expired.'], 400);
        }

        // Verify the hash
        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json(['message' => 'Invalid verification link.'], 400);
        }

        // Verify the signature
        $expectedSignature = hash_hmac('sha256', $id . $hash . $expires, config('app.key'));
        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Invalid verification signature.'], 400);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            
            // Generate token for the user
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully!',
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'role' => $user->role,
                    'whatsapp' => $user->whatsapp,
                    'avatar_url' => $user->avatar_url,
                    'bio' => $user->bio,
                    'company_name' => $user->company_name,
                    'student_verified' => $user->student_verified,
                    'student_expires_at' => $user->student_expires_at,
                    'gender' => $user->gender,
                    'state' => $user->state,
                    'language' => $user->language,
                    'has_premium' => $user->has_premium,
                    'premium_expires_at' => $user->premium_expires_at,
                    'free_trial_expires_at' => $user->free_trial_expires_at,
                ]
            ]);
        }

        return response()->json(['message' => 'Verification failed.'], 400);
    }

    // Handle email verification via authenticated request
    public function verify(EmailVerificationRequest $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return response()->json(['message' => 'Email verified successfully.']);
    }

    // Check if email is verified
    public function check(Request $request)
    {
        return response()->json([
            'success' => true,
            'email_verified' => $request->user()->hasVerifiedEmail()
        ]);
    }
}