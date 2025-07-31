<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\PortfolioItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PortfolioController extends Controller
{
    const MAX_FILES_PER_UPLOAD = 5;
    const MAX_TOTAL_FILES = 12;
    const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    const ACCEPTED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/jpg',
        'video/mp4',
        'video/quicktime',
        'video/mov'
    ];

    /**
     * Get user's portfolio
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can have portfolios'
            ], 403);
        }

        $portfolio = $user->portfolio()->with(['items' => function ($query) {
            $query->orderBy('order');
        }])->first();

        if (!$portfolio) {
            // Create empty portfolio if it doesn't exist
            $portfolio = $user->portfolio()->create([
                'title' => null,
                'bio' => null,
                'profile_picture' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'portfolio' => $portfolio,
                'items_count' => $portfolio->getItemsCount(),
                'images_count' => $portfolio->getImagesCount(),
                'videos_count' => $portfolio->getVideosCount(),
                'is_complete' => $portfolio->isComplete(),
            ]
        ]);
    }

    /**
     * Update portfolio profile information
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can update portfolios'
            ], 403);
        }

        // Log the request data for debugging
        Log::info('Portfolio update request', [
            'all_data' => $request->all(),
            'title' => $request->input('title'),
            'bio' => $request->input('bio'),
            'has_file' => $request->hasFile('profile_picture'),
            'content_type' => $request->header('Content-Type'),
        ]);

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:500',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // 5MB
        ]);

        if ($validator->fails()) {
            Log::error('Portfolio validation failed', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $portfolio = $user->portfolio()->firstOrCreate();

            $data = $request->only(['title', 'bio']);

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                // Delete old profile picture if exists
                if ($portfolio->profile_picture) {
                    Storage::disk('public')->delete($portfolio->profile_picture);
                }

                $file = $request->file('profile_picture');
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('portfolios/profile-pictures', $fileName, 'public');
                
                $data['profile_picture'] = $filePath;
            }

            Log::info('Updating portfolio with data', $data);

            $portfolio->update($data);

            // Notify admin of portfolio update
            \App\Services\NotificationService::notifyAdminOfPortfolioUpdate($user, 'profile_update', [
                'portfolio_id' => $portfolio->id,
                'title' => $data['title'] ?? null,
                'bio' => $data['bio'] ?? null,
                'has_profile_picture' => isset($data['profile_picture']),
            ]);

            $updatedPortfolio = $portfolio->fresh();
            Log::info('Portfolio updated successfully', [
                'portfolio_id' => $updatedPortfolio->id,
                'title' => $updatedPortfolio->title,
                'bio' => $updatedPortfolio->bio,
                'profile_picture' => $updatedPortfolio->profile_picture,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Portfolio profile updated successfully',
                'data' => $updatedPortfolio
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update portfolio profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update portfolio profile'
            ], 500);
        }
    }

    /**
     * Upload portfolio media files
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can upload portfolio media'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'files.*' => 'required|file|mimes:jpeg,png,jpg,mp4,mov,quicktime|max:51200', // 50MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $portfolio = $user->portfolio()->firstOrCreate();
            
            // Check total files limit
            $currentCount = $portfolio->items()->count();
            $uploadedFiles = $request->file('files', []);
            
            if ($currentCount + count($uploadedFiles) > self::MAX_TOTAL_FILES) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum ' . self::MAX_TOTAL_FILES . ' files allowed in portfolio'
                ], 422);
            }

            $uploadedItems = [];

            foreach ($uploadedFiles as $file) {
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('portfolios/media', $fileName, 'public');
                
                $mimeType = $file->getMimeType();
                $mediaType = $this->getMediaType($mimeType);
                
                Log::info('Processing uploaded file', [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mimeType,
                    'detected_media_type' => $mediaType,
                    'file_size' => $file->getSize(),
                ]);
                
                $item = $portfolio->items()->create([
                    'file_path' => $filePath,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $mimeType,
                    'media_type' => $mediaType,
                    'file_size' => $file->getSize(),
                    'order' => $portfolio->items()->max('order') + 1,
                ]);

                $uploadedItems[] = $item;
            }

            // Notify admin of portfolio media upload
            \App\Services\NotificationService::notifyAdminOfPortfolioUpdate($user, 'media_upload', [
                'portfolio_id' => $portfolio->id,
                'uploaded_count' => count($uploadedItems),
                'total_items' => $portfolio->items()->count(),
                'uploaded_items' => $uploadedItems,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Media uploaded successfully',
                'data' => [
                    'items' => $uploadedItems,
                    'total_items' => $portfolio->items()->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload portfolio media: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload media'
            ], 500);
        }
    }

    /**
     * Update portfolio item
     */
    public function updateItem(Request $request, PortfolioItem $item): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can update portfolio items'
            ], 403);
        }

        // Check if user owns this portfolio item
        if ($item->portfolio->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $item->update($request->only(['title', 'description', 'order']));

            return response()->json([
                'success' => true,
                'message' => 'Portfolio item updated successfully',
                'data' => $item->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update portfolio item: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update portfolio item'
            ], 500);
        }
    }

    /**
     * Delete portfolio item
     */
    public function deleteItem(PortfolioItem $item): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can delete portfolio items'
            ], 403);
        }

        // Check if user owns this portfolio item
        if ($item->portfolio->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            // Delete file from storage
            Storage::disk('public')->delete($item->file_path);
            
            // Delete item from database
            $item->delete();

            return response()->json([
                'success' => true,
                'message' => 'Portfolio item deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete portfolio item: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete portfolio item'
            ], 500);
        }
    }

    /**
     * Reorder portfolio items
     */
    public function reorderItems(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can reorder portfolio items'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'item_orders' => 'required|array',
            'item_orders.*.id' => 'required|exists:portfolio_items,id',
            'item_orders.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $portfolio = $user->portfolio()->first();
            
            if (!$portfolio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Portfolio not found'
                ], 404);
            }

            foreach ($request->item_orders as $itemOrder) {
                $item = $portfolio->items()->find($itemOrder['id']);
                if ($item) {
                    $item->update(['order' => $itemOrder['order']]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Portfolio items reordered successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reorder portfolio items: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder portfolio items'
            ], 500);
        }
    }

    /**
     * Get portfolio statistics
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can view portfolio statistics'
            ], 403);
        }

        $portfolio = $user->portfolio()->with('items')->first();

        if (!$portfolio) {
            return response()->json([
                'success' => false,
                'message' => 'Portfolio not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_items' => $portfolio->getItemsCount(),
                'images_count' => $portfolio->getImagesCount(),
                'videos_count' => $portfolio->getVideosCount(),
                'is_complete' => $portfolio->isComplete(),
                'has_minimum_items' => $portfolio->hasMinimumItems(),
                'profile_complete' => !empty($portfolio->title) && !empty($portfolio->bio),
            ]
        ]);
    }

    /**
     * Helper method to determine media type
     */
    private function getMediaType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        return 'other';
    }
} 