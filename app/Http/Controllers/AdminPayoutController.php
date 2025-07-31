<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Withdrawal;
use App\Models\JobPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class AdminPayoutController extends Controller
{
    /**
     * Get payout dashboard metrics
     */
    public function getPayoutMetrics(): JsonResponse
    {
        try {
            $metrics = [
                'total_pending_withdrawals' => Withdrawal::where('status', 'pending')->count(),
                'total_processing_withdrawals' => Withdrawal::where('status', 'processing')->count(),
                'total_completed_withdrawals' => Withdrawal::where('status', 'completed')->count(),
                'total_failed_withdrawals' => Withdrawal::where('status', 'failed')->count(),
                'total_pending_amount' => Withdrawal::where('status', 'pending')->sum('amount'),
                'total_processing_amount' => Withdrawal::where('status', 'processing')->sum('amount'),
                'contracts_waiting_review' => Contract::where('status', 'completed')
                    ->where('workflow_status', 'waiting_review')->count(),
                'contracts_payment_available' => Contract::where('status', 'completed')
                    ->where('workflow_status', 'payment_available')->count(),
                'contracts_payment_withdrawn' => Contract::where('status', 'completed')
                    ->where('workflow_status', 'payment_withdrawn')->count(),
                'total_platform_fees' => JobPayment::where('status', 'completed')->sum('platform_fee'),
                'total_creator_payments' => JobPayment::where('status', 'completed')->sum('creator_amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching payout metrics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payout metrics',
            ], 500);
        }
    }

    /**
     * Get pending withdrawals for admin review
     */
    public function getPendingWithdrawals(Request $request): JsonResponse
    {
        try {
            $withdrawals = Withdrawal::with(['creator:id,name,email,avatar_url'])
                ->whereIn('status', ['pending', 'processing'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            $withdrawals->getCollection()->transform(function ($withdrawal) {
                return [
                    'id' => $withdrawal->id,
                    'amount' => $withdrawal->formatted_amount,
                    'withdrawal_method' => $withdrawal->withdrawal_method_label,
                    'status' => $withdrawal->status,
                    'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                    'days_since_created' => $withdrawal->days_since_created,
                    'creator' => [
                        'id' => $withdrawal->creator->id,
                        'name' => $withdrawal->creator->name,
                        'email' => $withdrawal->creator->email,
                        'avatar_url' => $withdrawal->creator->avatar_url,
                    ],
                    'withdrawal_details' => $withdrawal->withdrawal_details,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $withdrawals,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching pending withdrawals', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending withdrawals',
            ], 500);
        }
    }

    /**
     * Process a withdrawal (admin action)
     */
    public function processWithdrawal(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $withdrawal = Withdrawal::with('creator')->find($id);

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal not found',
                ], 404);
            }

            if ($request->action === 'approve') {
                if ($withdrawal->process()) {
                    Log::info('Admin processed withdrawal', [
                        'withdrawal_id' => $withdrawal->id,
                        'admin_id' => Auth::id(),
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Withdrawal processed successfully',
                        'data' => [
                            'withdrawal_id' => $withdrawal->id,
                            'status' => $withdrawal->status,
                        ],
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to process withdrawal',
                    ], 500);
                }
            } else {
                // Reject withdrawal
                if ($withdrawal->cancel($request->reason)) {
                    Log::info('Admin rejected withdrawal', [
                        'withdrawal_id' => $withdrawal->id,
                        'admin_id' => Auth::id(),
                        'reason' => $request->reason,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Withdrawal rejected successfully',
                        'data' => [
                            'withdrawal_id' => $withdrawal->id,
                            'status' => $withdrawal->status,
                        ],
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to reject withdrawal',
                    ], 500);
                }
            }

        } catch (\Exception $e) {
            Log::error('Error processing withdrawal', [
                'withdrawal_id' => $id,
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process withdrawal',
            ], 500);
        }
    }

    /**
     * Get contracts with disputes
     */
    public function getDisputedContracts(): JsonResponse
    {
        try {
            $contracts = Contract::with(['brand:id,name,email,avatar_url', 'creator:id,name,email,avatar_url'])
                ->where('status', 'disputed')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            $contracts->getCollection()->transform(function ($contract) {
                return [
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'description' => $contract->description,
                    'budget' => $contract->formatted_budget,
                    'status' => $contract->status,
                    'workflow_status' => $contract->workflow_status,
                    'created_at' => $contract->created_at->format('Y-m-d H:i:s'),
                    'brand' => [
                        'id' => $contract->brand->id,
                        'name' => $contract->brand->name,
                        'email' => $contract->brand->email,
                        'avatar_url' => $contract->brand->avatar_url,
                    ],
                    'creator' => [
                        'id' => $contract->creator->id,
                        'name' => $contract->creator->name,
                        'email' => $contract->creator->email,
                        'avatar_url' => $contract->creator->avatar_url,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $contracts,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching disputed contracts', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch disputed contracts',
            ], 500);
        }
    }

    /**
     * Resolve a contract dispute (admin action)
     */
    public function resolveDispute(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'resolution' => 'required|in:complete,cancel,refund',
            'reason' => 'required|string|max:1000',
            'winner' => 'required|in:brand,creator,platform',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $contract = Contract::with(['brand', 'creator'])->find($id);

            if (!$contract || $contract->status !== 'disputed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Disputed contract not found',
                ], 404);
            }

            $resolution = $request->resolution;
            $reason = $request->reason;
            $winner = $request->winner;

            switch ($resolution) {
                case 'complete':
                    // Mark contract as completed and process payment
                    $contract->update([
                        'status' => 'completed',
                        'workflow_status' => 'waiting_review',
                    ]);
                    break;

                case 'cancel':
                    // Cancel contract and refund if necessary
                    $contract->update([
                        'status' => 'cancelled',
                        'cancellation_reason' => $reason,
                    ]);
                    break;

                case 'refund':
                    // Process refund based on winner
                    if ($winner === 'creator') {
                        // Refund to creator
                        $contract->update([
                            'status' => 'cancelled',
                            'cancellation_reason' => $reason,
                        ]);
                    } elseif ($winner === 'brand') {
                        // Complete contract in favor of brand
                        $contract->update([
                            'status' => 'completed',
                            'workflow_status' => 'waiting_review',
                        ]);
                    }
                    break;
            }

            Log::info('Admin resolved contract dispute', [
                'contract_id' => $contract->id,
                'admin_id' => Auth::id(),
                'resolution' => $resolution,
                'winner' => $winner,
                'reason' => $reason,
            ]);

            // Notify both parties about dispute resolution
            NotificationService::notifyUsersOfDisputeResolution($contract, $resolution, $winner, $reason);

            return response()->json([
                'success' => true,
                'message' => 'Dispute resolved successfully',
                'data' => [
                    'contract_id' => $contract->id,
                    'resolution' => $resolution,
                    'winner' => $winner,
                    'new_status' => $contract->status,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error resolving dispute', [
                'contract_id' => $id,
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve dispute',
            ], 500);
        }
    }

    /**
     * Get payout history
     */
    public function getPayoutHistory(Request $request): JsonResponse
    {
        try {
            $withdrawals = Withdrawal::with(['creator:id,name,email,avatar_url'])
                ->where('status', 'completed')
                ->orderBy('processed_at', 'desc')
                ->paginate(50);

            $withdrawals->getCollection()->transform(function ($withdrawal) {
                return [
                    'id' => $withdrawal->id,
                    'amount' => $withdrawal->formatted_amount,
                    'withdrawal_method' => $withdrawal->withdrawal_method_label,
                    'transaction_id' => $withdrawal->transaction_id,
                    'processed_at' => $withdrawal->processed_at->format('Y-m-d H:i:s'),
                    'creator' => [
                        'id' => $withdrawal->creator->id,
                        'name' => $withdrawal->creator->name,
                        'email' => $withdrawal->creator->email,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $withdrawals,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching payout history', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payout history',
            ], 500);
        }
    }
}
