<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Test endpoint for debugging FormData parsing
     */
    public function testFormData(Request $request): JsonResponse
    {
        error_log("=== FORM DATA TEST ===");
        error_log("Content-Type: " . $request->header('Content-Type'));
        error_log("Request method: " . $request->method());
        error_log("Request all: " . json_encode($request->all()));
        error_log("Request files: " . json_encode($request->allFiles()));
        error_log("Raw content length: " . strlen($request->getContent()));
        error_log("Raw content preview: " . substr($request->getContent(), 0, 500));
        
        // Check if it's multipart
        $contentType = $request->header('Content-Type');
        $isMultipart = strpos($contentType, 'multipart/form-data') !== false;
        error_log("Is multipart: " . ($isMultipart ? 'true' : 'false'));
        
        // Try manual parsing if needed
        if ($isMultipart && empty($request->all()) && !empty($request->getContent())) {
            error_log("Attempting manual parsing...");
            $parsedData = $this->parseMultipartData($request);
            error_log("Manually parsed: " . json_encode($parsedData));
            
            return response()->json([
                'success' => true,
                'message' => 'FormData test completed',
                'data' => [
                    'content_type' => $contentType,
                    'is_multipart' => $isMultipart,
                    'laravel_parsed' => $request->all(),
                    'laravel_files' => $request->allFiles(),
                    'manual_parsed' => $parsedData,
                    'raw_content_length' => strlen($request->getContent())
                ]
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'FormData test completed',
            'data' => [
                'content_type' => $contentType,
                'is_multipart' => $isMultipart,
                'laravel_parsed' => $request->all(),
                'laravel_files' => $request->allFiles(),
                'raw_content_length' => strlen($request->getContent())
            ]
        ]);
    }

    /**
     * Get the current user's profile
     */
    public function show(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'profile' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'whatsapp' => $user->whatsapp,
                    'avatar' => $user->avatar_url,
                    'bio' => $user->bio,
                    'company_name' => $user->company_name,
                    'gender' => $user->gender,
                    'state' => $user->state, // Return state directly instead of mapping to location
                    'language' => $user->language,
                    'languages' => $user->language ? [$user->language] : ['English'],
                    'categories' => ['General'], // Default categories
                    'has_premium' => $user->has_premium,
                    'student_verified' => $user->student_verified,
                    'premium_expires_at' => $user->premium_expires_at,
                    'free_trial_expires_at' => $user->free_trial_expires_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'message' => 'Profile retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the current user's profile
     */
    public function update(Request $request): JsonResponse
    {
        // Enhanced debug logging for FormData
        error_log("Content-Type: " . $request->header('Content-Type'));
        error_log("Request method: " . $request->method());
        error_log("Request role: " . $request->input('role'));
        error_log("Request state: " . $request->input('state'));
        error_log("Request all data: " . json_encode($request->all()));
        error_log("Request files: " . json_encode($request->allFiles()));
        error_log("Raw content length: " . strlen($request->getContent()));
        
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Check if this is a multipart form data request
            $contentType = $request->header('Content-Type');
            $isMultipart = strpos($contentType, 'multipart/form-data') !== false;
            
            error_log("Is multipart: " . ($isMultipart ? 'true' : 'false'));

            // If it's multipart but no data is parsed, try manual parsing
            if ($isMultipart && empty($request->all()) && !empty($request->getContent())) {
                error_log("Attempting manual multipart parsing");
                $parsedData = $this->parseMultipartData($request);
                error_log("Manually parsed data: " . json_encode($parsedData));
                
                // Merge manually parsed data with request
                foreach ($parsedData as $key => $value) {
                    if ($key !== 'avatar') {
                        $request->merge([$key => $value]);
                    }
                }
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => [
                    'sometimes',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id),
                ],
                'role' => 'sometimes|string|max:255',
                'whatsapp' => 'sometimes|string|max:20',
                'bio' => 'sometimes|string|max:1000',
                'company_name' => 'sometimes|string|max:255',
                'gender' => 'sometimes|string|max:50',
                'state' => 'sometimes|string|max:255', // Accept state directly instead of location
                'language' => 'sometimes|string|max:50',
                'languages' => 'sometimes|string', // JSON string for multiple languages
                'categories' => 'sometimes|string', // JSON string for categories
                'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Handle avatar upload
            $hasAvatarFile = false;
            $avatarFile = null;
            
            if ($request->hasFile('avatar')) {
                $hasAvatarFile = true;
                $avatarFile = $request->file('avatar');
                error_log("Avatar file found via hasFile()");
            } else {
                // Try manual multipart parsing for avatar
                if ($isMultipart && !empty($request->getContent())) {
                    $parsedData = $this->parseMultipartData($request);
                    if (isset($parsedData['avatar']) && $parsedData['avatar'] instanceof \Illuminate\Http\UploadedFile) {
                        $hasAvatarFile = true;
                        $avatarFile = $parsedData['avatar'];
                        error_log("Avatar file found via manual parsing");
                    }
                }
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Handle avatar upload
            if ($hasAvatarFile && $avatarFile) {
                // Delete old avatar if exists
                if ($user->avatar_url && Storage::disk('public')->exists(str_replace('/storage/', '', $user->avatar_url))) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar_url));
                }

                // Store new avatar
                $avatarPath = $avatarFile->store('avatars', 'public');
                $data['avatar_url'] = '/storage/' . $avatarPath;
            }

            // Handle languages and categories
            if (isset($data['languages'])) {
                $languages = json_decode($data['languages'], true);
                $data['language'] = is_array($languages) && !empty($languages) ? $languages[0] : 'English';
            }

            // Map gender values from frontend to backend
            if (isset($data['gender'])) {
                $genderMapping = [
                    'Female' => 'female',
                    'Male' => 'male',
                    'Non-binary' => 'other',
                    'Prefer not to say' => 'other'
                ];
                $data['gender'] = $genderMapping[$data['gender']] ?? $data['gender'];
            }

            // Validate role field - use input() for FormData
            $role = $request->input('role');
            if ($role) {
                $validRoles = ['creator', 'brand', 'admin', 'student'];
                if (!in_array($role, $validRoles)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid role value. Must be one of: ' . implode(', ', $validRoles)
                    ], 422);
                }
                $data['role'] = $role;
            }

            // Handle state field - use input() for FormData
            $state = $request->input('state');
            if ($state) {
                $data['state'] = $state;
            }
            
            // Remove fields that shouldn't be updated directly
            unset($data['languages'], $data['categories']);

            // Update user
            $user->update($data);

            // Refresh user data from database
            $user->refresh();

            return response()->json([
                'success' => true,
                'profile' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'whatsapp' => $user->whatsapp,
                    'avatar' => $user->avatar_url,
                    'bio' => $user->bio,
                    'company_name' => $user->company_name,
                    'gender' => $user->gender,
                    'location' => $user->state,
                    'language' => $user->language,
                    'languages' => $user->language ? [$user->language] : ['English'],
                    'categories' => ['General'],
                    'has_premium' => $user->has_premium,
                    'student_verified' => $user->student_verified,
                    'premium_expires_at' => $user->premium_expires_at,
                    'free_trial_expires_at' => $user->free_trial_expires_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'message' => 'Profile updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse multipart form data manually
     */
    private function parseMultipartData(Request $request): array
    {
        $rawContent = $request->getContent();
        $contentType = $request->header('Content-Type');
        
        // Extract boundary
        if (!preg_match('/boundary=(.+)$/', $contentType, $matches)) {
            return [];
        }
        
        $boundary = '--' . trim($matches[1]);
        $parts = explode($boundary, $rawContent);
        $parsedData = [];
        
        foreach ($parts as $part) {
            if (empty(trim($part)) || $part === '--') {
                continue;
            }
            
            // Parse headers
            $headerEnd = strpos($part, "\r\n\r\n");
            if ($headerEnd === false) {
                continue;
            }
            
            $headers = substr($part, 0, $headerEnd);
            $content = substr($part, $headerEnd + 4);
            $content = rtrim($content, "\r\n-");
            
            // Extract field name
            if (preg_match('/name="([^"]+)"/', $headers, $matches)) {
                $fieldName = $matches[1];
                
                // Check if it's a file
                if (preg_match('/filename="([^"]+)"/', $headers, $matches)) {
                    $filename = $matches[1];
                    
                    if (!empty($content)) {
                        // Create temporary file
                        $tempPath = tempnam(sys_get_temp_dir(), 'upload_');
                        file_put_contents($tempPath, $content);
                        
                        // Create UploadedFile object
                        $parsedData[$fieldName] = new \Illuminate\Http\UploadedFile(
                            $tempPath,
                            $filename,
                            mime_content_type($tempPath),
                            null,
                            true
                        );
                    }
                } else {
                    // Regular field
                    $parsedData[$fieldName] = $content;
                }
            }
        }
        
        return $parsedData;
    }
} 