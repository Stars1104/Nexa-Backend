<?php

namespace App\Http\Controllers;

use App\Models\CreatorBalance;
use App\Models\Withdrawal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CreatorBalanceController extends Controller
{
    /**
     * Get creator balance and earnings
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can access balance information',
            ], 403);
        }

        try {
            $balance = CreatorBalance::where('creator_id', $user->id)->first();

            if (!$balance) {
                // Create balance record if it doesn't exist
                $balance = CreatorBalance::create([
                    'creator_id' => $user->id,
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                ]);
            }

            // Get recent transactions
            $recentTransactions = $balance->getRecentTransactions(5);
            $recentWithdrawals = $balance->getWithdrawalHistory(5);

            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => [
                        'available_balance' => $balance->available_balance,
                        'pending_balance' => $balance->pending_balance,
                        'total_balance' => $balance->total_balance,
                        'total_earned' => $balance->total_earned,
                        'total_withdrawn' => $balance->total_withdrawn,
                        'formatted_available_balance' => $balance->formatted_available_balance,
                        'formatted_pending_balance' => $balance->formatted_pending_balance,
                        'formatted_total_balance' => $balance->formatted_total_balance,
                        'formatted_total_earned' => $balance->formatted_total_earned,
                        'formatted_total_withdrawn' => $balance->formatted_total_withdrawn,
                    ],
                    'earnings' => [
                        'this_month' => $balance->earnings_this_month,
                        'this_year' => $balance->earnings_this_year,
                        'formatted_this_month' => $balance->formatted_earnings_this_month,
                        'formatted_this_year' => $balance->formatted_earnings_this_year,
                    ],
                    'withdrawals' => [
                        'pending_count' => $balance->pending_withdrawals_count,
                        'pending_amount' => $balance->pending_withdrawals_amount,
                        'formatted_pending_amount' => $balance->formatted_pending_withdrawals_amount,
                    ],
                    'recent_transactions' => $recentTransactions->map(function ($payment) {
                        return [
                            'id' => $payment->id,
                            'contract_title' => $payment->contract->title,
                            'amount' => $payment->formatted_creator_amount,
                            'status' => $payment->status,
                            'processed_at' => $payment->processed_at?->format('Y-m-d H:i:s'),
                        ];
                    }),
                    'recent_withdrawals' => $recentWithdrawals->map(function ($withdrawal) {
                        return [
                            'id' => $withdrawal->id,
                            'amount' => $withdrawal->formatted_amount,
                            'method' => $withdrawal->withdrawal_method_label,
                            'status' => $withdrawal->status,
                            'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                        ];
                    }),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching creator balance', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch balance information',
            ], 500);
        }
    }

    /**
     * Get detailed balance history
     */
    public function history(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => 'nullable|integer|min:1|max:365',
            'type' => 'nullable|in:earnings,withdrawals,all',
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
                'message' => 'Only creators can access balance history',
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

            $days = $request->get('days', 30);
            $type = $request->get('type', 'all');

            $history = [];

            if ($type === 'all' || $type === 'earnings') {
                $earnings = $balance->payments()
                    ->with('contract:id,title')
                    ->where('status', 'completed')
                    ->when($days < 365, function ($query) use ($days) {
                        return $query->where('processed_at', '>=', now()->subDays($days));
                    })
                    ->orderBy('processed_at', 'desc')
                    ->get()
                    ->map(function ($payment) {
                        return [
                            'type' => 'earning',
                            'id' => $payment->id,
                            'amount' => $payment->creator_amount,
                            'formatted_amount' => $payment->formatted_creator_amount,
                            'description' => 'Payment for: ' . $payment->contract->title,
                            'date' => $payment->processed_at->format('Y-m-d H:i:s'),
                            'status' => $payment->status,
                        ];
                    });

                $history = array_merge($history, $earnings->toArray());
            }

            if ($type === 'all' || $type === 'withdrawals') {
                $withdrawals = $balance->withdrawals()
                    ->when($days < 365, function ($query) use ($days) {
                        return $query->where('created_at', '>=', now()->subDays($days));
                    })
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($withdrawal) {
                        return [
                            'type' => 'withdrawal',
                            'id' => $withdrawal->id,
                            'amount' => -$withdrawal->amount, // Negative for withdrawals
                            'formatted_amount' => '-' . $withdrawal->formatted_amount,
                            'description' => 'Withdrawal via ' . $withdrawal->withdrawal_method_label,
                            'date' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                            'status' => $withdrawal->status,
                        ];
                    });

                $history = array_merge($history, $withdrawals->toArray());
            }

            // Sort by date (newest first)
            usort($history, function ($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            // Calculate running balance
            $runningBalance = 0;
            foreach (array_reverse($history) as &$item) {
                $runningBalance += $item['amount'];
                $item['running_balance'] = $runningBalance;
                $item['formatted_running_balance'] = 'R$ ' . number_format($runningBalance, 2, ',', '.');
            }

            // Reverse back to newest first
            $history = array_reverse($history);

            return response()->json([
                'success' => true,
                'data' => [
                    'history' => $history,
                    'summary' => [
                        'total_earnings' => $balance->total_earned,
                        'total_withdrawals' => $balance->total_withdrawn,
                        'current_balance' => $balance->total_balance,
                        'formatted_total_earnings' => $balance->formatted_total_earned,
                        'formatted_total_withdrawals' => $balance->formatted_total_withdrawn,
                        'formatted_current_balance' => $balance->formatted_total_balance,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching balance history', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch balance history',
            ], 500);
        }
    }

    /**
     * Get withdrawal methods available for the creator
     */
    public function withdrawalMethods(): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can access withdrawal methods',
            ], 403);
        }

        try {
            $methods = [
                [
                    'id' => 'bank_transfer',
                    'name' => 'Transferência Bancária',
                    'description' => 'Transferência para conta bancária',
                    'min_amount' => 50.00,
                    'max_amount' => 10000.00,
                    'processing_time' => '2-3 dias úteis',
                    'fee' => 0.00,
                ],
                [
                    'id' => 'pagarme_account',
                    'name' => 'Conta Pagar.me',
                    'description' => 'Transferência para conta Pagar.me',
                    'min_amount' => 10.00,
                    'max_amount' => 5000.00,
                    'processing_time' => '1-2 dias úteis',
                    'fee' => 0.00,
                ],
                [
                    'id' => 'pix',
                    'name' => 'PIX',
                    'description' => 'Transferência PIX',
                    'min_amount' => 5.00,
                    'max_amount' => 2000.00,
                    'processing_time' => 'Até 24 horas',
                    'fee' => 0.00,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $methods,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching withdrawal methods', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch withdrawal methods',
            ], 500);
        }
    }

    /**
     * Get creator's work history
     */
    public function workHistory(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can access work history',
            ], 403);
        }

        try {
            $contracts = $user->creatorContracts()
                ->with(['brand:id,name,avatar_url', 'payment', 'review'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            $contracts->getCollection()->transform(function ($contract) {
                return [
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'description' => $contract->description,
                    'budget' => $contract->formatted_budget,
                    'creator_amount' => $contract->formatted_creator_amount,
                    'status' => $contract->status,
                    'started_at' => $contract->started_at->format('Y-m-d H:i:s'),
                    'completed_at' => $contract->completed_at?->format('Y-m-d H:i:s'),
                    'brand' => [
                        'id' => $contract->brand->id,
                        'name' => $contract->brand->name,
                        'avatar_url' => $contract->brand->avatar_url,
                    ],
                    'payment' => $contract->payment ? [
                        'status' => $contract->payment->status,
                        'amount' => $contract->payment->formatted_creator_amount,
                        'processed_at' => $contract->payment->processed_at?->format('Y-m-d H:i:s'),
                    ] : null,
                    'review' => $contract->review ? [
                        'rating' => $contract->review->rating,
                        'comment' => $contract->review->comment,
                        'created_at' => $contract->review->created_at->format('Y-m-d H:i:s'),
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $contracts,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching work history', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch work history',
            ], 500);
        }
    }
} 