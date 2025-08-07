<?php

namespace App\Http\Controllers;

use App\Models\CampaignTimeline;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CampaignTimelineController extends Controller
{
    /**
     * Get timeline for a contract
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'contract_id' => 'required|exists:contracts,id',
        ]);

        $contract = Contract::findOrFail($request->contract_id);
        
        // Check if user has access to this contract
        if (Auth::user()->role === 'brand' && $contract->brand_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        if (Auth::user()->role === 'creator' && $contract->creator_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $timeline = $contract->timeline()->orderBy('deadline')->get();

        return response()->json([
            'success' => true,
            'data' => $timeline,
        ]);
    }

    /**
     * Create timeline milestones for a contract
     */
    public function createMilestones(Request $request): JsonResponse
    {
        $request->validate([
            'contract_id' => 'required|exists:contracts,id',
        ]);

        $contract = Contract::findOrFail($request->contract_id);
        
        // Only brand can create milestones
        if (Auth::user()->role !== 'brand' || $contract->brand_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if timeline already exists
        if ($contract->timeline()->exists()) {
            return response()->json(['error' => 'Timeline already exists for this contract'], 400);
        }

        // Calculate deadlines based on offer creation date and estimated days
        $startDate = $contract->offer->created_at ?? now();
        $totalDays = $contract->estimated_days ?? 7;
        
        $milestones = [
            [
                'milestone_type' => 'script_submission',
                'title' => 'Script Submission',
                'description' => 'Submit the initial script for review',
                'deadline' => $startDate->copy()->addDays(ceil($totalDays * 0.25)),
            ],
            [
                'milestone_type' => 'script_approval',
                'title' => 'Script Approval',
                'description' => 'Approve the submitted script',
                'deadline' => $startDate->copy()->addDays(ceil($totalDays * 0.35)),
            ],
            [
                'milestone_type' => 'video_submission',
                'title' => 'Video Submission',
                'description' => 'Submit the final video',
                'deadline' => $startDate->copy()->addDays(ceil($totalDays * 0.85)),
            ],
            [
                'milestone_type' => 'final_approval',
                'title' => 'Final Approval',
                'description' => 'Approve the final video',
                'deadline' => $startDate->copy()->addDays($totalDays),
            ],
        ];

        $createdMilestones = [];
        foreach ($milestones as $milestone) {
            $createdMilestones[] = $contract->timeline()->create($milestone);
        }

        return response()->json([
            'success' => true,
            'data' => $createdMilestones,
            'message' => 'Timeline milestones created successfully',
        ]);
    }

    /**
     * Upload file for a milestone
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            'milestone_id' => 'required|exists:campaign_timelines,id',
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        $milestone = CampaignTimeline::findOrFail($request->milestone_id);
        $contract = $milestone->contract;
        
        // Check if user has access
        if (Auth::user()->role === 'brand' && $contract->brand_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        if (Auth::user()->role === 'creator' && $contract->creator_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Only creator can upload files for submission milestones
        if (Auth::user()->role !== 'creator' && in_array($milestone->milestone_type, ['script_submission', 'video_submission'])) {
            return response()->json(['error' => 'Only creator can upload files for submission milestones'], 403);
        }

        if (!$milestone->canUploadFile()) {
            return response()->json(['error' => 'Cannot upload file for this milestone'], 400);
        }

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $fileType = $file->getMimeType();
        
        // Store file
        $filePath = $file->store('timeline-files', 'public');

        $milestone->uploadFile($filePath, $fileName, $fileSize, $fileType);

        return response()->json([
            'success' => true,
            'data' => $milestone->fresh(),
            'message' => 'File uploaded successfully',
        ]);
    }

    /**
     * Approve a milestone
     */
    public function approveMilestone(Request $request): JsonResponse
    {
        $request->validate([
            'milestone_id' => 'required|exists:campaign_timelines,id',
            'comment' => 'nullable|string|max:500',
        ]);

        $milestone = CampaignTimeline::findOrFail($request->milestone_id);
        $contract = $milestone->contract;
        
        // Only brand can approve milestones
        if (Auth::user()->role !== 'brand' || $contract->brand_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$milestone->canBeApproved()) {
            return response()->json(['error' => 'Cannot approve this milestone'], 400);
        }

        $milestone->markAsApproved($request->comment);

        return response()->json([
            'success' => true,
            'data' => $milestone->fresh(),
            'message' => 'Milestone approved successfully',
        ]);
    }

    /**
     * Complete a milestone
     */
    public function completeMilestone(Request $request): JsonResponse
    {
        $request->validate([
            'milestone_id' => 'required|exists:campaign_timelines,id',
        ]);

        $milestone = CampaignTimeline::findOrFail($request->milestone_id);
        $contract = $milestone->contract;
        
        // Check if user has access
        if (Auth::user()->role === 'brand' && $contract->brand_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        if (Auth::user()->role === 'creator' && $contract->creator_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$milestone->canBeCompleted()) {
            return response()->json(['error' => 'Cannot complete this milestone'], 400);
        }

        $milestone->markAsCompleted();

        return response()->json([
            'success' => true,
            'data' => $milestone->fresh(),
            'message' => 'Milestone completed successfully',
        ]);
    }

    /**
     * Justify delay for a milestone
     */
    public function justifyDelay(Request $request): JsonResponse
    {
        $request->validate([
            'milestone_id' => 'required|exists:campaign_timelines,id',
            'justification' => 'required|string|max:1000',
        ]);

        $milestone = CampaignTimeline::findOrFail($request->milestone_id);
        $contract = $milestone->contract;
        
        // Only brand can justify delays
        if (Auth::user()->role !== 'brand' || $contract->brand_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$milestone->canJustifyDelay()) {
            return response()->json(['error' => 'Cannot justify delay for this milestone'], 400);
        }

        $milestone->justifyDelay($request->justification);

        return response()->json([
            'success' => true,
            'data' => $milestone->fresh(),
            'message' => 'Delay justified successfully',
        ]);
    }

    /**
     * Mark milestone as delayed
     */
    public function markAsDelayed(Request $request): JsonResponse
    {
        $request->validate([
            'milestone_id' => 'required|exists:campaign_timelines,id',
            'justification' => 'nullable|string|max:1000',
        ]);

        $milestone = CampaignTimeline::findOrFail($request->milestone_id);
        $contract = $milestone->contract;
        
        // Check if user has access
        if (Auth::user()->role === 'brand' && $contract->brand_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        if (Auth::user()->role === 'creator' && $contract->creator_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $milestone->markAsDelayed($request->justification);

        return response()->json([
            'success' => true,
            'data' => $milestone->fresh(),
            'message' => 'Milestone marked as delayed',
        ]);
    }

    /**
     * Download file for a milestone
     */
    public function downloadFile(Request $request): JsonResponse
    {
        $request->validate([
            'milestone_id' => 'required|exists:campaign_timelines,id',
        ]);

        $milestone = CampaignTimeline::findOrFail($request->milestone_id);
        $contract = $milestone->contract;
        
        // Check if user has access
        if (Auth::user()->role === 'brand' && $contract->brand_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        if (Auth::user()->role === 'creator' && $contract->creator_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$milestone->file_path) {
            return response()->json(['error' => 'No file available for download'], 404);
        }

        $filePath = storage_path('app/public/' . $milestone->file_path);
        
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'download_url' => Storage::url($milestone->file_path),
                'file_name' => $milestone->file_name,
                'file_size' => $milestone->file_size,
                'file_type' => $milestone->file_type,
            ],
        ]);
    }

    /**
     * Extend timeline deadline
     */
    public function extendTimeline(Request $request): JsonResponse
    {
        $request->validate([
            'milestone_id' => 'required|exists:campaign_timelines,id',
            'extension_days' => 'required|integer|min:1|max:365',
            'extension_reason' => 'required|string|max:1000',
        ]);

        $milestone = CampaignTimeline::findOrFail($request->milestone_id);
        $contract = $milestone->contract;
        
        // Only brand can extend timeline
        if (Auth::user()->role !== 'brand' || $contract->brand_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $milestone->extendTimeline(
            $request->extension_days,
            $request->extension_reason,
            Auth::id()
        );

        return response()->json([
            'success' => true,
            'data' => $milestone->fresh(),
            'message' => 'Timeline extended successfully',
        ]);
    }

    /**
     * Get timeline statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'contract_id' => 'required|exists:contracts,id',
        ]);

        $contract = Contract::findOrFail($request->contract_id);
        
        // Check if user has access to this contract
        if (Auth::user()->role === 'brand' && $contract->brand_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        if (Auth::user()->role === 'creator' && $contract->creator_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $timeline = $contract->timeline;
        
        $statistics = [
            'total_milestones' => $timeline->count(),
            'completed_milestones' => $timeline->where('status', 'completed')->count(),
            'pending_milestones' => $timeline->where('status', 'pending')->count(),
            'approved_milestones' => $timeline->where('status', 'approved')->count(),
            'delayed_milestones' => $timeline->where('is_delayed', true)->count(),
            'overdue_milestones' => $timeline->filter(function ($milestone) {
                return $milestone->isOverdue();
            })->count(),
            'progress_percentage' => $timeline->count() > 0 ? 
                round(($timeline->where('status', 'completed')->count() / $timeline->count()) * 100) : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }
} 