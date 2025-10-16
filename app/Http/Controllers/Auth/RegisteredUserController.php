<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        // Debug: Log all request data
        \Log::info('Registration request received', [
            'content_type' => $request->header('Content-Type'),
            'has_files' => !empty($request->allFiles()),
            'all_files' => $request->allFiles(),
            'has_avatar' => $request->hasFile('avatar_url'),
            'request_method' => $request->method(),
            'input_keys' => array_keys($request->all()),
            'all_data' => $request->all(),
        ]);

        // Check if email exists in soft-deleted users before validation
        $softDeletedUser = User::withTrashed()
            ->where('email', strtolower(trim($request->email)))
            ->whereNotNull('deleted_at')
            ->first();

        if ($softDeletedUser) {
            $daysSinceDeletion = now()->diffInDays($softDeletedUser->deleted_at);
            if ($daysSinceDeletion <= 30) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este e-mail está associado a uma conta que foi removida recentemente. Você pode restaurar sua conta em vez de criar uma nova.',
                    'can_restore' => true,
                    'removed_at' => $softDeletedUser->deleted_at->toISOString(),
                    'days_since_deletion' => $daysSinceDeletion,
                ], 422);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Este e-mail está associado a uma conta que foi removida há mais de 30 dias. Entre em contato com o suporte para mais informações.',
                    'can_restore' => false,
                ], 422);
            }
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[\p{L}\p{M}\s\.\'\-]+$/u'
                ],
            'email' => [
                'required', 
                'string', 
                'lowercase', 
                'email', 
                'max:255', 
                'unique:'.User::class,
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            ],
            'password' => [
                'required', 
                'confirmed', 
                'min:8',
                'max:128',
                Rules\Password::defaults()
            ],
            'role' => [
                'required',
                'nullable', 
                'string', 
                Rule::in(['creator', 'brand', 'admin']),
                'max:20'
            ],
            'whatsapp' => [
                'nullable', 
                'string', 
                'max:20',
                'regex:/^[\+]?[1-9][\d]{0,15}$/'
            ],
            'avatar_url' => [
                'nullable', 
                'image', 
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048', // 2MB max file size
                'dimensions:min_width=100,min_height=100,max_width=1024,max_height=1024'
            ],
            'bio' => [
                'nullable', 
                'string', 
                'max:1000',
                'min:10'
            ],
            'company_name' => [
                'nullable', 
                'string', 
                'max:255',
                'min:2',
                'regex:/^[a-zA-Z0-9\s\-\.\&]+$/'
            ],
            'gender' => [
                'nullable', 
                'string', 
                Rule::in(['male', 'female', 'other']),
                'max:10'
            ],
            'state' => [
                'nullable', 
                'string', 
                'max:100',
                'regex:/^[a-zA-Z\s\-]+$/'
            ],
            'language' => [
                'nullable', 
                'string', 
                'max:10',
                Rule::in(['en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja', 'ko', 'ar'])
            ],
            'has_premium' => [
                'nullable',
                'boolean'
            ],
            'isStudent' => [
                'nullable',
                'boolean'
            ],
        ], [
            'name.required' => 'The name field is required.',
            'name.min' => 'The name must be at least 2 characters.',
            'name.regex' => 'The name can only contain letters, spaces, hyphens, dots, and apostrophes.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'email.regex' => 'Please enter a valid email address format.',
            'password.required' => 'The password field is required.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.max' => 'The password may not be greater than 128 characters.',
            'role.in' => 'The selected role is invalid.',
            'whatsapp.regex' => 'Please enter a valid phone number.',
            'avatar_url.image' => 'The avatar must be an image file.',
            'avatar_url.mimes' => 'The avatar must be a file of type: jpeg, png, jpg, gif, webp.',
            'avatar_url.max' => 'The avatar may not be greater than 2MB.',
            'avatar_url.dimensions' => 'The avatar must be between 100x100 and 1024x1024 pixels.',
            'bio.min' => 'The bio must be at least 10 characters.',
            'bio.max' => 'The bio may not be greater than 1000 characters.',
            'company_name.min' => 'The company name must be at least 2 characters.',
            'company_name.regex' => 'The company name can only contain letters, numbers, spaces, hyphens, dots, and ampersands.',
            'gender.in' => 'The selected gender is invalid.',
            'state.regex' => 'The state can only contain letters, spaces, and hyphens.',
            'language.in' => 'The selected language is not supported.',
            'has_premium.boolean' => 'The premium status must be true or false.',
        ]);

        // Additional custom validation logic
        $this->validateCustomRules($request);
        
        \Log::info('Validation passed successfully');

        // Handle avatar upload
        $avatarUrl = null;
        if ($request->hasFile('avatar_url')) {
            \Log::info('Avatar file detected', [
                'filename' => $request->file('avatar_url')->getClientOriginalName(),
                'size' => $request->file('avatar_url')->getSize(),
                'mime' => $request->file('avatar_url')->getMimeType()
            ]);
            $avatarUrl = $this->uploadAvatar($request->file('avatar_url'));
            \Log::info('Avatar URL generated: ' . $avatarUrl);
        } else {
            \Log::info('No avatar file in request');
        }

        \Log::info('About to create user with data', [
            'name' => trim($request->name),
            'email' => strtolower(trim($request->email)),
            'role' => $request->role ?? 'creator',
            'gender' => $request->gender ?? 'other',
            'birth_date' => $request->birth_date ?? null,
        ]);
        
        // Determine if user is a student
        $isStudent = $request->isStudent ?? false;
        
        // Set free trial only for students (1 month), creators and brands get no free trial
        $freeTrialExpiresAt = $isStudent ? now()->addYear() : null;
        
        $user = User::create([
            'name' => trim($request->name),
            'email' => strtolower(trim($request->email)),
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'creator',
            'whatsapp' => $request->whatsapp ? $this->formatPhoneNumber($request->whatsapp) : null,
            'avatar_url' => $avatarUrl,
            'bio' => $request->bio ? trim($request->bio) : null,
            'company_name' => $request->company_name ? trim($request->company_name) : null,
            'student_verified' => false, // Will be set to true after verification
            'student_expires_at' => null,
            'gender' => $request->gender ?? 'other',
            'birth_date' => $request->birth_date ?? null,
            'state' => $request->state ? trim($request->state) : null,
            'language' => null,
            'has_premium' => $request->has_premium ?? false,
            'premium_expires_at' => null,
            'free_trial_expires_at' => $freeTrialExpiresAt,
            'email_verified_at' => now(), // Automatically mark email as verified
        ]);
        
        \Log::info('User created successfully', ['user_id' => $user->id]);

        // Notify admin of new user registration
        \App\Services\NotificationService::notifyAdminOfNewRegistration($user);
        
        // Generate token for immediate login
        $token = $user->createToken('auth_token')->plainTextToken;
        
        \Log::info('User registration completed successfully', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful! Your account has been created and you are now logged in.',
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
                'student_expires_at' => $user->free_trial_expires_at,
                'gender' => $user->gender,
                'state' => $user->state,
                'language' => $user->language,
                'has_premium' => $user->has_premium,
                'premium_expires_at' => $user->premium_expires_at,
                'free_trial_expires_at' => $user->free_trial_expires_at,
                'isStudent' => $isStudent,
            ]
        ], 201);
    }

    /**
     * Upload avatar image and return the URL
     */
    private function uploadAvatar($file): string
    {
        try {
            // Generate a unique filename
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            \Log::info('Generated filename: ' . $filename);
            
            // Store the file in the public/avatars directory
            $path = $file->storeAs('avatars', $filename, 'public');
            \Log::info('File stored at path: ' . $path);
            
            // Return the full URL to the uploaded file
            $url = Storage::url($path);
            \Log::info('Storage URL: ' . $url);
            
            return $url;
        } catch (\Exception $e) {
            \Log::error('Avatar upload failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Custom validation rules
     */
    private function validateCustomRules(Request $request): void    
    {
        // Validate email domain if needed
        if ($request->email) {
            $domain = substr(strrchr($request->email, "@"), 1);
            $disallowedDomains = ['tempmail.com', '10minutemail.com', 'guerrillamail.com'];
            
            if (in_array(strtolower($domain), $disallowedDomains)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => ['Temporary email addresses are not allowed.']
                ]);
            }
        }

        // Validate password strength
        if ($request->password) {
            $password = $request->password;
            $errors = [];

            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = 'Password must contain at least one uppercase letter.';
            }
            if (!preg_match('/[a-z]/', $password)) {
                $errors[] = 'Password must contain at least one lowercase letter.';
            }
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = 'Password must contain at least one number.';
            }
            if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $errors[] = 'Password must contain at least one special character.';
            }

            if (!empty($errors)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'password' => $errors
                ]);
            }
        }

        // Validate phone number format
        if ($request->whatsapp) {
            $phone = $this->formatPhoneNumber($request->whatsapp);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'whatsapp' => ['Phone number must be between 10 and 15 digits.']
                ]);
            }
        }

        // Validate bio content
        if ($request->bio) {
            $bio = $request->bio;
            $forbiddenWords = ['spam', 'advertisement', 'promote'];
            
            foreach ($forbiddenWords as $word) {
                if (stripos($bio, $word) !== false) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'bio' => ['The bio contains inappropriate content.']
                    ]);
                }
            }
        }
    }

    /**
     * Format phone number to standard format
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Ensure it starts with +
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
}
