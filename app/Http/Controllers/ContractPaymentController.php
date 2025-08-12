<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\BrandPaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class ContractPaymentController extends Controller
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('PAGARME_API_KEY', '');
        $this->baseUrl = 'https://api.pagar.me/core/v5';
    }

    /**
     * Process payment when contract is started
     */
    public function processContractPayment(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if user is a brand
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can process contract payments',
            ], 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'contract_id' => 'required|exists:contracts,id,brand_id,' . $user->id,
            'payment_method_id' => 'nullable|exists:brand_payment_methods,id,brand_id,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contract = Contract::with(['brand', 'creator'])->find($request->contract_id);

        // Check if contract can be paid
        if ($contract->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Contract is not in active status',
            ], 400);
        }

        // Check if payment was already processed
        if ($contract->payment && $contract->payment->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Payment for this contract has already been processed',
            ], 400);
        }

        // Get payment method
        $paymentMethod = null;
        if ($request->payment_method_id) {
            $paymentMethod = BrandPaymentMethod::find($request->payment_method_id);
        } else {
            $paymentMethod = $user->defaultPaymentMethod;
        }

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'No payment method found. Please add a payment method first.',
            ], 400);
        }

        // Check if Pagar.me is configured
        if (empty($this->apiKey)) {
            Log::error('Pagar.me API key not configured');
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway not configured. Please contact support.',
            ], 503);
        }

        try {
            DB::beginTransaction();

            // Create order in Pagar.me
            $orderData = [
                'code' => 'CONTRACT_' . $contract->id . '_' . time(),
                'customer_id' => $paymentMethod->pagarme_customer_id,
                'items' => [
                    [
                        'amount' => (int)($contract->budget * 100), // Convert to cents
                        'description' => 'Contract: ' . $contract->title,
                        'quantity' => 1,
                        'code' => 'CONTRACT_' . $contract->id,
                    ]
                ],
                'payments' => [
                    [
                        'payment_method' => 'credit_card',
                        'credit_card' => [
                            'operation_type' => 'auth_and_capture',
                            'installments' => 1,
                            'statement_descriptor' => 'NEXA CONTRACT',
                            'card_id' => $paymentMethod->pagarme_card_id,
                        ]
                    ]
                ]
            ];

            // Make actual request to Pagar.me
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/orders', $orderData);

            if (!$response->successful()) {
                Log::error('Pagar.me order creation failed', [
                    'contract_id' => $contract->id,
                    'brand_id' => $user->id,
                    'response' => $response->json(),
                    'status' => $response->status()
                ]);

                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Payment processing failed. Please check your payment method and try again.',
                    'error' => $response->json()
                ], 400);
            }

            $paymentResponse = $response->json();

            $order = $paymentResponse;
            $charge = $order['charges'][0] ?? null;

            if (!$charge) {
                Log::error('No charge found in PagarMe response', [
                    'contract_id' => $contract->id,
                    'brand_id' => $user->id,
                    'response' => $paymentResponse
                ]);

                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Payment processing failed. No charge found in response.',
                    'error' => $paymentResponse
                ], 400);
            }

            // Create transaction record
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'pagarme_transaction_id' => $charge['id'],
                'pagarme_order_id' => $order['id'],
                'status' => $charge['status'],
                'amount' => $contract->budget,
                'payment_method' => 'credit_card',
                'card_brand' => $charge['payment_method_details']['card']['brand'] ?? $paymentMethod->card_brand,
                'card_last4' => $charge['payment_method_details']['card']['last_four_digits'] ?? $paymentMethod->card_last4,
                'card_holder_name' => $paymentMethod->card_holder_name,
                'payment_data' => $charge,
                'paid_at' => $charge['status'] === 'paid' ? now() : null,
                'contract_id' => $contract->id,
            ]);

            // Create job payment record
            $jobPayment = \App\Models\JobPayment::create([
                'contract_id' => $contract->id,
                'brand_id' => $contract->brand_id,
                'creator_id' => $contract->creator_id,
                'total_amount' => $contract->budget,
                'platform_fee' => $contract->budget * 0.05, // 5% platform fee
                'creator_amount' => $contract->budget * 0.95, // 95% for creator
                'payment_method' => 'credit_card',
                'status' => $charge['status'] === 'paid' ? 'paid' : 'pending',
                'transaction_id' => $transaction->id,
            ]);

            // Update contract status
            $contract->update([
                'status' => 'active',
                'workflow_status' => 'active',
                'started_at' => now(),
            ]);

            DB::commit();

            // Notify both parties
            NotificationService::notifyCreatorOfContractStarted($contract);
            NotificationService::notifyBrandOfContractStarted($contract);

            Log::info('Contract payment processed successfully', [
                'contract_id' => $contract->id,
                'brand_id' => $user->id,
                'creator_id' => $contract->creator_id,
                'amount' => $contract->budget,
                'transaction_id' => $transaction->id,
                'payment_status' => $charge['status']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contract payment processed successfully',
                'data' => [
                    'contract_id' => $contract->id,
                    'amount' => $contract->budget,
                    'payment_status' => $charge['status'],
                    'transaction_id' => $transaction->id,
                    'order_id' => $order['id'],
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error processing contract payment', [
                'contract_id' => $contract->id,
                'brand_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the payment. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get contract payment status
     */
    public function getContractPaymentStatus(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'contract_id' => 'required|exists:contracts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contract = Contract::with(['payment', 'payment.transaction'])->find($request->contract_id);

        // Check if user has access to this contract
        if ($contract->brand_id !== $user->id && $contract->creator_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $paymentData = null;
        if ($contract->payment) {
            $paymentData = [
                'status' => $contract->payment->status,
                'total_amount' => $contract->payment->total_amount,
                'platform_fee' => $contract->payment->platform_fee,
                'creator_amount' => $contract->payment->creator_amount,
                'payment_method' => $contract->payment->payment_method,
                'created_at' => $contract->payment->created_at,
                'transaction' => $contract->payment->transaction ? [
                    'id' => $contract->payment->transaction->id,
                    'status' => $contract->payment->transaction->status,
                    'paid_at' => $contract->payment->transaction->paid_at,
                ] : null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'contract_id' => $contract->id,
                'contract_status' => $contract->status,
                'workflow_status' => $contract->workflow_status,
                'budget' => $contract->budget,
                'payment' => $paymentData,
            ]
        ]);
    }

    /**
     * Get available payment methods for contract
     */
    public function getAvailablePaymentMethods(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if user is a brand
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can access payment methods',
            ], 403);
        }

        $paymentMethods = BrandPaymentMethod::where('brand_id', $user->id)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $paymentMethods->map(function ($method) {
                return [
                    'id' => $method->id,
                    'card_brand' => $method->card_brand,
                    'card_last4' => $method->card_last4,
                    'card_holder_name' => $method->card_holder_name,
                    'is_default' => $method->is_default,
                    'formatted_info' => $method->formatted_card_info,
                ];
            }),
        ]);
    }

    /**
     * Retry payment for failed contract
     */
    public function retryPayment(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if user is a brand
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can retry payments',
            ], 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'contract_id' => 'required|exists:contracts,id,brand_id,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contract = Contract::with(['brand', 'creator'])->find($request->contract_id);

        // Check if contract payment failed
        if (!$contract->isPaymentFailed()) {
            return response()->json([
                'success' => false,
                'message' => 'Contract is not in payment failed status',
            ], 400);
        }

        // Retry payment
        $success = $contract->retryPayment();

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Payment retry successful',
                'data' => [
                    'contract_id' => $contract->id,
                    'status' => $contract->status,
                    'workflow_status' => $contract->workflow_status,
                ],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Payment retry failed. Please check your payment method.',
            ], 400);
        }
    }
} 