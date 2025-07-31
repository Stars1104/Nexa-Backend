<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class ContractController extends Controller
{
    /**
     * Get contracts for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $status = $request->get('status'); // 'active', 'completed', 'cancelled', 'disputed'

        try {
            $query = $user->isBrand() 
                ? $user->brandContracts() 
                : $user->creatorContracts();

            if ($status) {
                $query->where('status', $status);
            }

            $contracts = $query->with(['brand:id,name,avatar_url', 'creator:id,name,avatar_url', 'offer'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            $contracts->getCollection()->transform(function ($contract) use ($user) {
                $otherUser = $user->isBrand() ? $contract->creator : $contract->brand;
                
                return [
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'description' => $contract->description,
                    'budget' => $contract->formatted_budget,
                    'creator_amount' => $contract->formatted_creator_amount,
                    'platform_fee' => $contract->formatted_platform_fee,
                    'estimated_days' => $contract->estimated_days,
                    'requirements' => $contract->requirements,
                    'status' => $contract->status,
                    'workflow_status' => $contract->workflow_status,
                    'started_at' => $contract->started_at->format('Y-m-d H:i:s'),
                    'expected_completion_at' => $contract->expected_completion_at->format('Y-m-d H:i:s'),
                    'completed_at' => $contract->completed_at?->format('Y-m-d H:i:s'),
                    'cancelled_at' => $contract->cancelled_at?->format('Y-m-d H:i:s'),
                    'cancellation_reason' => $contract->cancellation_reason,
                    'days_until_completion' => $contract->days_until_completion,
                    'progress_percentage' => $contract->progress_percentage,
                    'is_overdue' => $contract->isOverdue(),
                    'is_near_completion' => $contract->is_near_completion,
                    'can_be_completed' => $contract->canBeCompleted(),
                    'can_be_cancelled' => $contract->canBeCancelled(),
                    'is_waiting_for_review' => $contract->isWaitingForReview(),
                    'is_payment_available' => $contract->isPaymentAvailable(),
                    'is_payment_withdrawn' => $contract->isPaymentWithdrawn(),
                    'has_brand_review' => $contract->has_brand_review,
                    'has_creator_review' => $contract->has_creator_review,
                    'has_both_reviews' => $contract->has_both_reviews,
                    'creator' => [
                        'id' => $contract->creator->id,
                        'name' => $contract->creator->name,
                        'avatar_url' => $contract->creator->avatar_url,
                    ],
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar_url' => $otherUser->avatar_url,
                    ],
                    'payment' => $contract->payment ? [
                        'id' => $contract->payment->id,
                        'status' => $contract->payment->status,
                        'total_amount' => $contract->payment->formatted_total_amount,
                        'creator_amount' => $contract->payment->formatted_creator_amount,
                        'platform_fee' => $contract->payment->formatted_platform_fee,
                        'processed_at' => $contract->payment->processed_at?->format('Y-m-d H:i:s'),
                    ] : null,
                    'review' => $contract->review ? [
                        'id' => $contract->review->id,
                        'rating' => $contract->review->rating,
                        'comment' => $contract->review->comment,
                        'created_at' => $contract->review->created_at->format('Y-m-d H:i:s'),
                    ] : null,
                    'created_at' => $contract->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $contracts,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching contracts', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contracts',
            ], 500);
        }
    }

    /**
     * Get a specific contract
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $contract = Contract::with(['brand:id,name,avatar_url', 'creator:id,name,avatar_url', 'offer'])
                ->where(function ($query) use ($user) {
                    $query->where('brand_id', $user->id)
                          ->orWhere('creator_id', $user->id);
                })
                ->find($id);

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contract not found or access denied',
                ], 404);
            }

            $otherUser = $user->isBrand() ? $contract->creator : $contract->brand;
            
            $contractData = [
                'id' => $contract->id,
                'title' => $contract->title,
                'description' => $contract->description,
                'budget' => $contract->formatted_budget,
                'creator_amount' => $contract->formatted_creator_amount,
                'platform_fee' => $contract->formatted_platform_fee,
                'estimated_days' => $contract->estimated_days,
                'requirements' => $contract->requirements,
                'status' => $contract->status,
                'workflow_status' => $contract->workflow_status,
                'started_at' => $contract->started_at->format('Y-m-d H:i:s'),
                'expected_completion_at' => $contract->expected_completion_at->format('Y-m-d H:i:s'),
                'completed_at' => $contract->completed_at?->format('Y-m-d H:i:s'),
                'cancelled_at' => $contract->cancelled_at?->format('Y-m-d H:i:s'),
                'cancellation_reason' => $contract->cancellation_reason,
                'days_until_completion' => $contract->days_until_completion,
                'progress_percentage' => $contract->progress_percentage,
                'is_overdue' => $contract->isOverdue(),
                'is_near_completion' => $contract->is_near_completion,
                'can_be_completed' => $contract->can_be_completed,
                'can_be_cancelled' => $contract->can_be_cancelled,
                'is_waiting_for_review' => $contract->isWaitingForReview(),
                'is_payment_available' => $contract->isPaymentAvailable(),
                'is_payment_withdrawn' => $contract->isPaymentWithdrawn(),
                'has_brand_review' => $contract->has_brand_review,
                'has_creator_review' => $contract->has_creator_review,
                'has_both_reviews' => $contract->has_both_reviews,
                'creator' => [
                    'id' => $contract->creator->id,
                    'name' => $contract->creator->name,
                    'avatar_url' => $contract->creator->avatar_url,
                ],
                'other_user' => [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'avatar_url' => $otherUser->avatar_url,
                ],
                'payment' => $contract->payment ? [
                    'id' => $contract->payment->id,
                    'status' => $contract->payment->status,
                    'total_amount' => $contract->payment->formatted_total_amount,
                    'creator_amount' => $contract->payment->formatted_creator_amount,
                    'platform_fee' => $contract->payment->formatted_platform_fee,
                    'processed_at' => $contract->payment->processed_at?->format('Y-m-d H:i:s'),
                ] : null,
                'review' => $contract->review ? [
                    'id' => $contract->review->id,
                    'rating' => $contract->review->rating,
                    'comment' => $contract->review->comment,
                    'created_at' => $contract->review->created_at->format('Y-m-d H:i:s'),
                ] : null,
                'created_at' => $contract->created_at->format('Y-m-d H:i:s'),
            ];

            return response()->json([
                'success' => true,
                'data' => $contractData,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching contract', [
                'user_id' => $user->id,
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contract',
            ], 500);
        }
    }

    /**
     * Get contracts for a specific chat room
     */
    public function getContractsForChatRoom(Request $request, string $roomId): JsonResponse
    {
        $user = Auth::user();
        
        // Find the chat room and verify user has access
        $chatRoom = \App\Models\ChatRoom::where('room_id', $roomId)
            ->where(function ($query) use ($user) {
                $query->where('brand_id', $user->id)
                      ->orWhere('creator_id', $user->id);
            })
            ->first();

        if (!$chatRoom) {
            return response()->json([
                'success' => false,
                'message' => 'Chat room not found or access denied',
            ], 404);
        }

        try {
            // Get contracts for this chat room (through offers)
            $contracts = Contract::whereHas('offer', function ($query) use ($chatRoom) {
                $query->where('chat_room_id', $chatRoom->id);
            })
            ->with(['brand:id,name,avatar_url', 'creator:id,name,avatar_url', 'offer'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($contract) use ($user) {
                $otherUser = $user->isBrand() ? $contract->creator : $contract->brand;
                
                return [
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'description' => $contract->description,
                    'budget' => $contract->formatted_budget,
                    'creator_amount' => $contract->formatted_creator_amount,
                    'platform_fee' => $contract->formatted_platform_fee,
                    'estimated_days' => $contract->estimated_days,
                    'requirements' => $contract->requirements,
                    'status' => $contract->status,
                    'started_at' => $contract->started_at->format('Y-m-d H:i:s'),
                    'expected_completion_at' => $contract->expected_completion_at->format('Y-m-d H:i:s'),
                    'completed_at' => $contract->completed_at?->format('Y-m-d H:i:s'),
                    'cancelled_at' => $contract->cancelled_at?->format('Y-m-d H:i:s'),
                    'cancellation_reason' => $contract->cancellation_reason,
                    'days_until_completion' => $contract->days_until_completion,
                    'progress_percentage' => $contract->progress_percentage,
                    'is_overdue' => $contract->isOverdue(),
                    'is_near_completion' => $contract->is_near_completion,
                    'can_be_completed' => $contract->canBeCompleted(),
                    'can_be_cancelled' => $contract->canBeCancelled(),
                    'has_brand_review' => $contract->has_brand_review,
                    'has_creator_review' => $contract->has_creator_review,
                    'has_both_reviews' => $contract->has_both_reviews,
                    'creator' => [
                        'id' => $contract->creator->id,
                        'name' => $contract->creator->name,
                        'avatar_url' => $contract->creator->avatar_url,
                    ],
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar_url' => $otherUser->avatar_url,
                    ],
                    'payment' => $contract->payment ? [
                        'id' => $contract->payment->id,
                        'status' => $contract->payment->status,
                        'total_amount' => $contract->payment->formatted_total_amount,
                        'creator_amount' => $contract->payment->formatted_creator_amount,
                        'platform_fee' => $contract->payment->formatted_platform_fee,
                        'processed_at' => $contract->payment->processed_at?->format('Y-m-d H:i:s'),
                    ] : null,
                    'review' => $contract->review ? [
                        'id' => $contract->review->id,
                        'rating' => $contract->review->rating,
                        'comment' => $contract->review->comment,
                        'created_at' => $contract->review->created_at->format('Y-m-d H:i:s'),
                    ] : null,
                    'created_at' => $contract->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $contracts,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching contracts for chat room', [
                'user_id' => $user->id,
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contracts',
            ], 500);
        }
    }

    /**
     * Complete a contract (brand only)
     */
    public function complete(int $id): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a brand
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can complete contracts',
            ], 403);
        }

        try {
            $contract = Contract::where('brand_id', $user->id)
                ->where('status', 'active')
                ->find($id);

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contract not found or cannot be completed',
                ], 404);
            }

            if (!$contract->canBeCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contract cannot be completed',
                ], 400);
            }

            if ($contract->complete()) {
                Log::info('Contract completed successfully', [
                    'contract_id' => $contract->id,
                    'brand_id' => $user->id,
                    'creator_id' => $contract->creator_id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Contract completed successfully! Please submit a review to release payment to the creator.',
                    'data' => [
                        'contract_id' => $contract->id,
                        'status' => $contract->status,
                        'workflow_status' => $contract->workflow_status,
                        'requires_review' => true,
                        'next_step' => 'submit_review',
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to complete contract',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error completing contract', [
                'user_id' => $user->id,
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete contract. Please try again.',
            ], 500);
        }
    }

    /**
     * Cancel a contract
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        try {
            $contract = Contract::where(function ($query) use ($user) {
                $query->where('brand_id', $user->id)
                      ->orWhere('creator_id', $user->id);
            })
            ->where('status', 'active')
            ->find($id);

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contract not found or cannot be cancelled',
                ], 404);
            }

            if (!$contract->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contract cannot be cancelled',
                ], 400);
            }

            if ($contract->cancel($request->reason)) {
                Log::info('Contract cancelled successfully', [
                    'contract_id' => $contract->id,
                    'user_id' => $user->id,
                    'reason' => $request->reason,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Contract cancelled successfully',
                    'data' => [
                        'contract_id' => $contract->id,
                        'status' => $contract->status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel contract',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error cancelling contract', [
                'user_id' => $user->id,
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel contract. Please try again.',
            ], 500);
        }
    }

    /**
     * Terminate a contract (brand only)
     */
    public function terminate(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        // Check if user is a brand
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can terminate contracts',
            ], 403);
        }

        try {
            $contract = Contract::where('brand_id', $user->id)
                ->where('status', 'active')
                ->find($id);

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contract not found or cannot be terminated',
                ], 404);
            }

            if (!$contract->canBeTerminated()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contract cannot be terminated',
                ], 400);
            }

            if ($contract->terminate($request->reason)) {
                Log::info('Contract terminated successfully', [
                    'contract_id' => $contract->id,
                    'brand_id' => $user->id,
                    'reason' => $request->reason,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Contract terminated successfully',
                    'data' => [
                        'contract_id' => $contract->id,
                        'status' => $contract->status,
                        'workflow_status' => $contract->workflow_status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to terminate contract',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error terminating contract', [
                'user_id' => $user->id,
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to terminate contract. Please try again.',
            ], 500);
        }
    }

    /**
     * Dispute a contract
     */
    public function dispute(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        try {
            $contract = Contract::where(function ($query) use ($user) {
                $query->where('brand_id', $user->id)
                      ->orWhere('creator_id', $user->id);
            })
            ->where('status', 'active')
            ->find($id);

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contract not found or cannot be disputed',
                ], 404);
            }

            if ($contract->dispute($request->reason)) {
                Log::info('Contract disputed successfully', [
                    'contract_id' => $contract->id,
                    'user_id' => $user->id,
                    'reason' => $request->reason,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Contract disputed successfully. Our team will review the case.',
                    'data' => [
                        'contract_id' => $contract->id,
                        'status' => $contract->status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to dispute contract',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error disputing contract', [
                'user_id' => $user->id,
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to dispute contract. Please try again.',
            ], 500);
        }
    }
} 