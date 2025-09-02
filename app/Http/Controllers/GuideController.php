<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuideRequest;
use App\Http\Resources\GuideResource;
use App\Models\Guide;
use App\Models\Step;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class GuideController extends Controller
{
    // List guides
    public function index()
    {
        $guides = Guide::with('steps')->latest()->paginate(15);
        return GuideResource::collection($guides);
    }



    // Store a new guide
    public function store(StoreGuideRequest $request)
    {
        try {
            Log::info('Guide Create Request - All data:', $request->all());
            Log::info('Guide Create Request - Has videoFile:', ['hasFile' => $request->hasFile('videoFile')]);
            Log::info('Guide Create Request - videoFile value:', ['videoFile' => $request->input('videoFile')]);
            Log::info('Guide Create Request - Files:', $request->allFiles());
            
            $data = $request->only(['title', 'audience', 'description']);
            
            // Check if user is authenticated
            if (!auth()->check()) {
                Log::error('User not authenticated for guide creation');
                return response()->json(['message' => 'User not authenticated'], 401);
            }
            
            $data['created_by'] = auth()->id(); // Track who created the guide
            Log::info('Guide Create Request - User ID:', ['user_id' => $data['created_by']]);

            // Note: Main guide video is no longer supported, only step videos
            $data['video_path'] = null;
            $data['video_mime'] = null;

            DB::beginTransaction();
            
            $guide = Guide::create($data);
            Log::info('Guide created successfully:', ['guide_id' => $guide->id]);

            // Handle steps if provided
            if ($request->has('steps') && is_array($request->steps)) {
                foreach ($request->steps as $index => $stepData) {
                    Log::info("Processing step {$index} complete data:", $stepData);
                    $stepFields = [
                        'guide_id' => $guide->id,
                        'title' => $stepData['title'],
                        'description' => $stepData['description'],
                        'order' => $index,
                    ];

                    // Handle step video if provided
                    if (isset($stepData['videoFile']) && $stepData['videoFile'] instanceof \Illuminate\Http\UploadedFile) {
                        $file = $stepData['videoFile'];
                        $filename = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
                        $path = $file->storeAs('videos/steps', $filename, 'public');
                        
                        $stepFields['video_path'] = $path;
                        $stepFields['video_mime'] = $file->getMimeType();
                    }

                    // Handle step screenshots if provided
                    if (isset($stepData['screenshots']) && is_array($stepData['screenshots'])) {
                        $screenshotPaths = [];
                        foreach ($stepData['screenshots'] as $screenshot) {
                            if ($screenshot instanceof \Illuminate\Http\UploadedFile) {
                                $filename = Str::uuid()->toString() . '.' . $screenshot->getClientOriginalExtension();
                                $path = $screenshot->storeAs('screenshots/steps', $filename, 'public');
                                $screenshotPaths[] = $path;
                            }
                        }
                        $stepFields['screenshots'] = $screenshotPaths;
                    }

                    Step::create($stepFields);
                }
            }

            DB::commit();

            // Reload guide with steps
            $guide->load('steps');

            return (new GuideResource($guide))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Guide creation failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to create guide',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Show a single guide
    public function show(Guide $guide)
    {
        $guide->load('steps');
        return new GuideResource($guide);
    }

    // Update a guide
    public function update(StoreGuideRequest $request, Guide $guide)
    {
        try {
            $data = $request->only(['title', 'audience', 'description']);

            // Note: Main guide video is no longer supported, only step videos
            $data['video_path'] = null;
            $data['video_mime'] = null;

            DB::beginTransaction();

            $guide->update($data);

            // Handle steps update if provided
            if ($request->has('steps') && is_array($request->steps)) {
                // Delete existing steps
                $guide->steps()->delete();

                // Create new steps
                foreach ($request->steps as $index => $stepData) {
                    $stepFields = [
                        'guide_id' => $guide->id,
                        'title' => $stepData['title'],
                        'description' => $stepData['description'],
                        'order' => $index,
                    ];

                    // Handle step video if provided
                    if (isset($stepData['videoFile']) && $stepData['videoFile'] instanceof \Illuminate\Http\UploadedFile) {
                        $file = $stepData['videoFile'];
                        $filename = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
                        $path = $file->storeAs('videos/steps', $filename, 'public');
                        
                        $stepFields['video_path'] = $path;
                        $stepFields['video_mime'] = $file->getMimeType();
                    }

                    // Handle step screenshots if provided
                    if (isset($stepData['screenshots']) && is_array($stepData['screenshots'])) {
                        $screenshotPaths = [];
                        foreach ($stepData['screenshots'] as $screenshot) {
                            if ($screenshot instanceof \Illuminate\Http\UploadedFile) {
                                $filename = Str::uuid()->toString() . '.' . $screenshot->getClientOriginalExtension();
                                $path = $screenshot->storeAs('screenshots/steps', $filename, 'public');
                                $screenshotPaths[] = $path;
                            }
                        }
                        $stepFields['screenshots'] = $screenshotPaths;
                    }

                    Step::create($stepFields);
                }
            }

            DB::commit();

            // Reload guide with steps
            $guide->load('steps');

            return new GuideResource($guide);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Guide update failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to update guide',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete a guide
    public function destroy(Guide $guide)
    {
        try {
            DB::beginTransaction();

            // Delete step videos
            foreach ($guide->steps as $step) {
                if ($step->video_path && Storage::disk('public')->exists($step->video_path)) {
                    Storage::disk('public')->delete($step->video_path);
                }
            }

            // Delete guide video
            if ($guide->video_path && Storage::disk('public')->exists($guide->video_path)) {
                Storage::disk('public')->delete($guide->video_path);
            }

            // Delete steps (cascade should handle this, but being explicit)
            $guide->steps()->delete();
            
            // Delete guide
            $guide->delete();

            DB::commit();

            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Guide deletion failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to delete guide',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}