<?php

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use App\Models\CreatorBalance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class WithdrawalController extends Controller
{
    /**
     * Create a withdrawal request
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:10|max:10000',
            'withdrawal_method' => 'required|in:bank_transfer,pagarme_account,pix',
            'withdrawal_details' => 'required|array',
            'withdrawal_details.bank' => 'required_if:withdrawal_method,bank_transfer|string|max:100',
            'withdrawal_details.agency' => 'required_if:withdrawal_method,bank_transfer|string|max:20',
            'withdrawal_details.account' => 'required_if:withdrawal_method,bank_transfer|string|max:20',
            'withdrawal_details.account_type' => 'required_if:withdrawal_method,bank_transfer|in:checking,savings',
            'withdrawal_details.holder_name' => 'required|string|max:255',
            'withdrawal_details.pix_key' => 'required_if:withdrawal_method,pix|string|max:255',
            'withdrawal_details.pix_key_type' => 'required_if:withdrawal_method,pix|in:cpf,cnpj,email,phone,random',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can request withdrawals',
            ], 403);
        }

        try {
            $balance = CreatorBalance::where('creator_id', $user->id)->first();

            if (!$balance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Balance not found',
                ], 404);
            }

            // Check if user has sufficient balance
            if (!$balance->canWithdraw($request->amount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient available balance for withdrawal',
                ], 400);
            }

            // Check minimum withdrawal amount based on method
            $minAmounts = [
                'bank_transfer' => 50.00,
                'pagarme_account' => 10.00,
                'pix' => 5.00,
            ];

            if ($request->amount < $minAmounts[$request->withdrawal_method]) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum withdrawal amount for ' . $request->withdrawal_method . ' is R$ ' . number_format($minAmounts[$request->withdrawal_method], 2, ',', '.'),
                ], 400);
            }

            // Check if user has pending withdrawals
            $pendingWithdrawals = $user->withdrawals()
                ->whereIn('status', ['pending', 'processing'])
                ->count();

            if ($pendingWithdrawals >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have too many pending withdrawals. Please wait for them to be processed.',
                ], 400);
            }

            $withdrawal = Withdrawal::create([
                'creator_id' => $user->id,
                'amount' => $request->amount,
                'withdrawal_method' => $request->withdrawal_method,
                'withdrawal_details' => $request->withdrawal_details,
                'status' => 'pending',
            ]);

            // Deduct from available balance
            $balance->withdraw($request->amount);

            Log::info('Withdrawal request created successfully', [
                'withdrawal_id' => $withdrawal->id,
                'creator_id' => $user->id,
                'amount' => $request->amount,
                'method' => $request->withdrawal_method,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully',
                'data' => [
                    'id' => $withdrawal->id,
                    'amount' => $withdrawal->formatted_amount,
                    'method' => $withdrawal->withdrawal_method_label,
                    'status' => $withdrawal->status,
                    'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating withdrawal request', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit withdrawal request. Please try again.',
            ], 500);
        }
    }

    /**
     * Get withdrawal history for the authenticated creator
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can access withdrawal history',
            ], 403);
        }

        try {
            $status = $request->get('status');
            $query = $user->withdrawals();

            if ($status) {
                $query->where('status', $status);
            }

            $withdrawals = $query->orderBy('created_at', 'desc')
                ->paginate(10);

            $withdrawals->getCollection()->transform(function ($withdrawal) {
                return [
                    'id' => $withdrawal->id,
                    'amount' => $withdrawal->formatted_amount,
                    'method' => $withdrawal->withdrawal_method_label,
                    'status' => $withdrawal->status,
                    'status_color' => $withdrawal->status_color,
                    'status_badge_color' => $withdrawal->status_badge_color,
                    'transaction_id' => $withdrawal->transaction_id,
                    'failure_reason' => $withdrawal->failure_reason,
                    'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                    'processed_at' => $withdrawal->processed_at?->format('Y-m-d H:i:s'),
                    'days_since_created' => $withdrawal->days_since_created,
                    'is_recent' => $withdrawal->is_recent,
                    'bank_account_info' => $withdrawal->bank_account_info,
                    'pix_info' => $withdrawal->pix_info,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $withdrawals,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching withdrawal history', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch withdrawal history',
            ], 500);
        }
    }

    /**
     * Get a specific withdrawal
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can access withdrawal details',
            ], 403);
        }

        try {
            $withdrawal = Withdrawal::where('creator_id', $user->id)
                ->find($id);

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $withdrawal->id,
                    'amount' => $withdrawal->formatted_amount,
                    'method' => $withdrawal->withdrawal_method_label,
                    'status' => $withdrawal->status,
                    'status_color' => $withdrawal->status_color,
                    'status_badge_color' => $withdrawal->status_badge_color,
                    'transaction_id' => $withdrawal->transaction_id,
                    'failure_reason' => $withdrawal->failure_reason,
                    'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                    'processed_at' => $withdrawal->processed_at?->format('Y-m-d H:i:s'),
                    'days_since_created' => $withdrawal->days_since_created,
                    'is_recent' => $withdrawal->is_recent,
                    'can_be_cancelled' => $withdrawal->canBeCancelled(),
                    'withdrawal_details' => $withdrawal->withdrawal_details,
                    'bank_account_info' => $withdrawal->bank_account_info,
                    'pix_info' => $withdrawal->pix_info,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching withdrawal details', [
                'user_id' => $user->id,
                'withdrawal_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch withdrawal details',
            ], 500);
        }
    }

    /**
     * Cancel a withdrawal request
     */
    public function cancel(int $id): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can cancel withdrawals',
            ], 403);
        }

        try {
            $withdrawal = Withdrawal::where('creator_id', $user->id)
                ->where('status', 'pending')
                ->find($id);

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal not found or cannot be cancelled',
                ], 404);
            }

            if (!$withdrawal->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal cannot be cancelled',
                ], 400);
            }

            if ($withdrawal->cancel()) {
                Log::info('Withdrawal cancelled successfully', [
                    'withdrawal_id' => $withdrawal->id,
                    'creator_id' => $user->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Withdrawal cancelled successfully',
                    'data' => [
                        'id' => $withdrawal->id,
                        'status' => $withdrawal->status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel withdrawal',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error cancelling withdrawal', [
                'user_id' => $user->id,
                'withdrawal_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel withdrawal. Please try again.',
            ], 500);
        }
    }

    /**
     * Get withdrawal statistics for the creator
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can access withdrawal statistics',
            ], 403);
        }

        try {
            $withdrawals = $user->withdrawals();

            $stats = [
                'total_withdrawals' => $withdrawals->count(),
                'total_amount_withdrawn' => $withdrawals->where('status', 'completed')->sum('amount'),
                'pending_withdrawals' => $withdrawals->where('status', 'pending')->count(),
                'pending_amount' => $withdrawals->where('status', 'pending')->sum('amount'),
                'processing_withdrawals' => $withdrawals->where('status', 'processing')->count(),
                'processing_amount' => $withdrawals->where('status', 'processing')->sum('amount'),
                'failed_withdrawals' => $withdrawals->where('status', 'failed')->count(),
                'cancelled_withdrawals' => $withdrawals->where('status', 'cancelled')->count(),
                'this_month' => $withdrawals->where('status', 'completed')
                    ->whereMonth('processed_at', now()->month)
                    ->whereYear('processed_at', now()->year)
                    ->sum('amount'),
                'this_year' => $withdrawals->where('status', 'completed')
                    ->whereYear('processed_at', now()->year)
                    ->sum('amount'),
            ];

            // Format amounts
            $stats['formatted_total_amount_withdrawn'] = 'R$ ' . number_format($stats['total_amount_withdrawn'], 2, ',', '.');
            $stats['formatted_pending_amount'] = 'R$ ' . number_format($stats['pending_amount'], 2, ',', '.');
            $stats['formatted_processing_amount'] = 'R$ ' . number_format($stats['processing_amount'], 2, ',', '.');
            $stats['formatted_this_month'] = 'R$ ' . number_format($stats['this_month'], 2, ',', '.');
            $stats['formatted_this_year'] = 'R$ ' . number_format($stats['this_year'], 2, ',', '.');

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching withdrawal statistics', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch withdrawal statistics',
            ], 500);
        }
    }
} 