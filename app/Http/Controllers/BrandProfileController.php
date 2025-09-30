<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BrandProfileController extends Controller
{
    /**
     * Get the current brand's profile
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

            if (!$user->isBrand()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Brand role required.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_url,
                    'avatar_url' => $user->avatar_url,
                    'company_name' => $user->company_name,
                    'whatsapp_number' => $user->whatsapp_number,
                    'gender' => $user->gender,
                    'state' => $user->state,
                    'languages' => $user->languages ? json_decode($user->languages, true) : [],
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the current brand's profile
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$user->isBrand()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Brand role required.'
                ], 403);
            }

            $contentType = $request->header('Content-Type');
            $isMultipart = strpos($contentType, 'multipart/form-data') !== false;
            
            error_log("Brand Profile - Is multipart: " . ($isMultipart ? 'true' : 'false'));

            if ($isMultipart && empty($request->all()) && !empty($request->getContent())) {
                error_log("Brand Profile - Attempting manual multipart parsing");
                $parsedData = $this->parseMultipartData($request);
                error_log("Brand Profile - Manually parsed data: " . json_encode($parsedData));
                
                foreach ($parsedData as $key => $value) {
                    if ($key !== 'avatar') {
                        $request->merge([$key => $value]);
                    }
                }
            }

            $validator = Validator::make($request->all(), [
                'username' => 'sometimes|string|max:255',
                'email' => [
                    'sometimes',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id),
                ],
                'company_name' => 'sometimes|string|max:255',
                'whatsapp_number' => 'sometimes|string|max:20',
                'gender' => 'sometimes|nullable|string|in:male,female,other',
                'state' => 'sometimes|nullable|string|max:255',
                'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $hasAvatarFile = false;
            $avatarFile = null;
            
            if ($request->hasFile('avatar')) {
                $hasAvatarFile = true;
                $avatarFile = $request->file('avatar');
                error_log("Brand Profile - Avatar file found via hasFile()");
            } else {
                if ($isMultipart && !empty($request->getContent())) {
                    $parsedData = $this->parseMultipartData($request);
                    if (isset($parsedData['avatar']) && $parsedData['avatar'] instanceof \Illuminate\Http\UploadedFile) {
                        $hasAvatarFile = true;
                        $avatarFile = $parsedData['avatar'];
                        error_log("Brand Profile - Avatar file found via manual parsing");
                    }
                }
            }

            if ($validator->fails()) {
                error_log("Brand Profile - Validation failed: " . json_encode($validator->errors()));
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            if (isset($data['username'])) {
                $data['name'] = $data['username'];
                unset($data['username']);
            }

            if ($hasAvatarFile && $avatarFile) {
                if ($user->avatar_url && Storage::disk('public')->exists(str_replace('/storage/', '', $user->avatar_url))) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar_url));
                }

                $avatarPath = $avatarFile->store('avatars', 'public');
                $data['avatar_url'] = '/storage/' . $avatarPath;
                error_log("Brand Profile - Avatar stored at: " . $data['avatar_url']);
            }

            unset($data['avatar']);

            $user->update($data);

            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_url,
                    'avatar_url' => $user->avatar_url,
                    'company_name' => $user->company_name,
                    'whatsapp_number' => $user->whatsapp_number,
                    'gender' => $user->gender,
                    'state' => $user->state,
                    'languages' => $user->languages ? json_decode($user->languages, true) : [],
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Brand Profile - Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change the current brand's password
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$user->isBrand()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Brand role required.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'old_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
                'new_password_confirmation' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if old password is correct
            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload avatar for the current brand
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$user->isBrand()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Brand role required.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'avatar' => 'required', // Allow both file and base64 string
            ]);

            // Manual multipart parsing workaround for avatar upload
            $hasAvatarFile = false;
            $avatarFile = null;
            $hasAvatarBase64 = false;
            $avatarBase64 = null;
            
            if (!$request->hasFile('avatar')) {
                // Check if avatar is sent as base64 string
                if ($request->has('avatar') && is_string($request->input('avatar'))) {
                    $avatarInput = $request->input('avatar');
                    if (strpos($avatarInput, 'data:image/') === 0) {
                        $hasAvatarBase64 = true;
                        $avatarBase64 = $avatarInput;
                    }
                }
                
                // Get the raw request content for file upload
                $rawContent = $request->getContent();
                $boundary = null;
                
                // Extract boundary from Content-Type header
                $contentType = $request->header('Content-Type');
                if (preg_match('/boundary=(.+)$/', $contentType, $matches)) {
                    $boundary = '--' . trim($matches[1]);
                }
                
                if ($boundary && strpos($rawContent, 'Content-Disposition: form-data; name="avatar"') !== false) {
                    // Split by boundary
                    $parts = explode($boundary, $rawContent);
                    foreach ($parts as $part) {
                        if (strpos($part, 'Content-Disposition: form-data; name="avatar"') !== false) {
                            // Extract filename
                            if (preg_match('/filename="([^"]+)"/', $part, $matches)) {
                                $filename = $matches[1];
                                
                                // Extract file content (everything after the headers)
                                $fileContent = substr($part, strpos($part, "\r\n\r\n") + 4);
                                $fileContent = rtrim($fileContent, "\r\n-");
                                
                                if (!empty($fileContent)) {
                                    $hasAvatarFile = true;
                                    
                                    // Create a temporary file
                                    $tempPath = tempnam(sys_get_temp_dir(), 'avatar_');
                                    file_put_contents($tempPath, $fileContent);
                                    
                                    // Create a Laravel UploadedFile object
                                    $avatarFile = new \Illuminate\Http\UploadedFile(
                                        $tempPath,
                                        $filename,
                                        mime_content_type($tempPath),
                                        null,
                                        true
                                    );
                                }
                            }
                            break;
                        }
                    }
                }
            } else {
                $hasAvatarFile = true;
                $avatarFile = $request->file('avatar');
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!$hasAvatarFile && !$hasAvatarBase64) {
                return response()->json([
                    'success' => false,
                    'message' => 'No avatar file or base64 data provided'
                ], 400);
            }

            $avatarUrl = null;

            // Handle avatar upload (file or base64)
            if ($hasAvatarFile && $avatarFile) {
                // Delete old avatar if exists
                if ($user->avatar_url && Storage::disk('public')->exists(str_replace('/storage/', '', $user->avatar_url))) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar_url));
                }

                // Store new avatar
                $avatarPath = $avatarFile->store('avatars', 'public');
                $avatarUrl = '/storage/' . $avatarPath;
                
                // Update user with new avatar URL
                $user->update(['avatar_url' => $avatarUrl]);
            } elseif ($hasAvatarBase64 && $avatarBase64) {
                // Handle base64 avatar
                $avatarResult = $this->handleAvatarUpload($avatarBase64, $user);
                if (!$avatarResult['success']) {
                    return response()->json($avatarResult, 400);
                }
                $avatarUrl = $avatarResult['avatar_url'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'avatar' => $avatarUrl,
                    'updated_at' => $user->updated_at
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete avatar for the current brand
     */
    public function deleteAvatar(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$user->isBrand()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Brand role required.'
                ], 403);
            }

            // Delete old avatar file if exists
            if ($user->avatar_url && Storage::disk('public')->exists(str_replace('/storage/', '', $user->avatar_url))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar_url));
            }

            // Update user to remove avatar
            $user->update(['avatar_url' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete avatar: ' . $e->getMessage()
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

    /**
     * Handle avatar upload from base64 data
     */
    private function handleAvatarUpload(string $base64Data, $user): array
    {
        // Check if it's a valid base64 image
        if (!preg_match('/^data:image\/(jpeg|png|jpg|gif|webp|svg\+xml);base64,/', $base64Data)) {
            return [
                'success' => false,
                'message' => 'Invalid image format. Please provide a valid base64 encoded image.'
            ];
        }

        try {
            // Extract the base64 data
            $base64Image = str_replace('data:image/jpeg;base64,', '', $base64Data);
            $base64Image = str_replace('data:image/png;base64,', '', $base64Image);
            $base64Image = str_replace('data:image/jpg;base64,', '', $base64Image);
            $base64Image = str_replace('data:image/gif;base64,', '', $base64Image);
            $base64Image = str_replace('data:image/webp;base64,', '', $base64Image);
            $base64Image = str_replace('data:image/svg+xml;base64,', '', $base64Image);

            // Decode the base64 data
            $imageData = base64_decode($base64Image);
            
            if ($imageData === false) {
                return [
                    'success' => false,
                    'message' => 'Invalid base64 data'
                ];
            }

            // Delete old avatar if exists
            if ($user->avatar_url && Storage::disk('public')->exists(str_replace('/storage/', '', $user->avatar_url))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar_url));
            }

            // Generate unique filename with appropriate extension
            $extension = 'jpg'; // default
            if (strpos($base64Data, 'data:image/svg+xml;') === 0) {
                $extension = 'svg';
            } elseif (strpos($base64Data, 'data:image/png;') === 0) {
                $extension = 'png';
            } elseif (strpos($base64Data, 'data:image/gif;') === 0) {
                $extension = 'gif';
            } elseif (strpos($base64Data, 'data:image/webp;') === 0) {
                $extension = 'webp';
            }
            
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $extension;
            $path = 'avatars/' . $filename;

            // Store the new avatar
            Storage::disk('public')->put($path, $imageData);

            // Update user with new avatar URL
            $avatarUrl = '/storage/' . $path;
            $user->update(['avatar_url' => $avatarUrl]);

            return [
                'success' => true,
                'avatar_url' => $avatarUrl
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process image: ' . $e->getMessage()
            ];
        }
    }
} 