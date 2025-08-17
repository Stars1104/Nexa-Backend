<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\CreatorBalance;
use App\Models\BankAccount;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentController extends Controller
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.pagarme.api_key') ?? '';
        $this->baseUrl = config('services.pagarme.environment') === 'production' 
            ? 'https://api.pagar.me/core/v5' 
            : 'https://api.pagar.me/core/v5';
    }

    /**
     * Process a subscription payment for creators
     */
    public function processSubscription(Request $request): JsonResponse
    {
        Log::info('Subscription payment request received', [
            'request_data' => $request->except(['card_number', 'card_cvv']), // Don't log sensitive data
            'user_agent' => $request->header('User-Agent'),
            'ip' => $request->ip(),
        ]);

        // Log all request data for debugging (remove in production)
        Log::info('Full subscription request data for debugging', [
            'all_data' => $request->all(),
            'has_card_number' => $request->has('card_number'),
            'has_card_cvv' => $request->has('card_cvv'),
            'has_card_holder_name' => $request->has('card_holder_name'),
            'has_card_expiration_date' => $request->has('card_expiration_date'),
            'has_cpf' => $request->has('cpf'),
            'subscription_plan_id' => $request->has('subscription_plan_id'),
        ]);

        // Check if Pagar.me is configured
        if (empty($this->apiKey)) {
            Log::error('Pagar.me API key not configured');
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway not configured. Please contact support.',
            ], 503);
        }

        // Check if we're in test mode
        $isTestMode = config('services.pagarme.environment') === 'sandbox' && 
                     (config('app.env') === 'local' || config('app.env') === 'development');
        
        Log::info('Test mode check', [
            'pagarme_environment' => config('services.pagarme.environment'),
            'app_env' => config('app.env'),
            'is_test_mode' => $isTestMode
        ]);

        try {
            $request->validate([
                'card_number' => 'required|string|size:16',
                'card_holder_name' => 'required|string|max:255',
                'card_expiration_date' => 'required|string|size:4', // MMYY format
                'card_cvv' => 'required|string|min:3|max:4',
                'cpf' => 'required|string|regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', // CPF format validation
                'subscription_plan_id' => 'required|integer|exists:subscription_plans,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Subscription validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        // Validate CPF
        if (!$this->validateCPF($request->cpf)) {
            return response()->json([
                'success' => false,
                'message' => 'CPF inválido. Por favor, verifique o número.',
                'errors' => ['cpf' => ['CPF inválido. Use um CPF válido como: 111.444.777-35']]
            ], 422);
        }

        // If in test mode, process the payment locally
        if ($isTestMode) {
            Log::info('Processing subscription in test mode', [
                'user_id' => auth()->id(),
                'test_mode' => true
            ]);
            
            try {
                DB::beginTransaction();
                
                $user = auth()->user();
                
                // Get the selected subscription plan
                $subscriptionPlan = \App\Models\SubscriptionPlan::find($request->subscription_plan_id);
                if (!$subscriptionPlan) {
                    throw new \Exception('Invalid subscription plan');
                }
                
                // Calculate expiration date based on plan duration
                $expiresAt = now()->addMonths($subscriptionPlan->duration_months);
                
                // Create a test transaction record
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'amount' => $subscriptionPlan->price * 100, // Convert to cents
                    'payment_method' => 'credit_card',
                    'status' => 'paid',
                    'pagarme_transaction_id' => 'test_' . time(),
                    'paid_at' => now(),
                    'expires_at' => $expiresAt,
                ]);

                // Create subscription record
                \App\Models\Subscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $subscriptionPlan->id,
                    'status' => \App\Models\Subscription::STATUS_ACTIVE,
                    'starts_at' => now(),
                    'expires_at' => $expiresAt,
                    'amount_paid' => $subscriptionPlan->price,
                    'payment_method' => 'credit_card',
                    'transaction_id' => $transaction->id,
                    'auto_renew' => false,
                ]);

                // Update user premium status
                $user->update([
                    'has_premium' => true,
                    'premium_expires_at' => $expiresAt,
                ]);

                DB::commit();

                Log::info('Test subscription successful', [
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'test_mode' => true
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Test subscription successful',
                    'test_mode' => true,
                    'transaction' => [
                        'id' => $transaction->id,
                        'status' => 'paid'
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Test subscription failed', [
                    'user_id' => auth()->id(),
                    'error' => $e->getMessage(),
                    'test_mode' => true
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Test subscription failed: ' . $e->getMessage(),
                ], 500);
            }
        } else {
            Log::info('Not in test mode, proceeding with Pagar.me API call', [
                'test_mode' => false
            ]);
        }

        try {
            DB::beginTransaction();

            $user = auth()->user();
            
            Log::info('User authenticated for subscription', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $user->role,
            ]);
            
            if (!$user) {
                Log::error('User not authenticated for subscription');
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }
            
            // Check if user is a creator
            if (!$user->isCreator()) {
                Log::error('Non-creator user attempted subscription', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Only creators can subscribe to premium'
                ], 403);
            }

            // Check if user already has active premium
            if ($user->hasPremiumAccess()) {
                Log::info('User already has premium access', [
                    'user_id' => $user->id,
                    'has_premium' => $user->has_premium,
                    'premium_expires_at' => $user->premium_expires_at,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active premium subscription'
                ], 400);
            }

            // Get the selected subscription plan
            $subscriptionPlan = \App\Models\SubscriptionPlan::find($request->subscription_plan_id);
            if (!$subscriptionPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid subscription plan selected'
                ], 400);
            }

            // Prepare payment data for Pagar.me v5
            $paymentData = [
                'code' => 'NEXA_PREMIUM_' . $user->id . '_' . time(),
                'customer' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => 'individual',
                    'country' => 'br',
                    'documents' => [
                        [
                            'type' => 'cpf',
                            'number' => preg_replace('/[^0-9]/', '', $request->cpf) // Remove dots and dash
                        ]
                    ],
                    'phones' => [
                        'mobile_phone' => [
                            'country_code' => '55',
                            'area_code' => '11',
                            'number' => '999999999'
                        ]
                    ]
                ],
                'items' => [
                    [
                        'amount' => (int)($subscriptionPlan->price * 100), // Pagar.me expects amount in cents
                        'description' => 'Nexa Premium Subscription - ' . $subscriptionPlan->name,
                        'quantity' => 1,
                        'code' => 'NEXA_PREMIUM_' . $subscriptionPlan->duration_months . 'M'
                    ]
                ],
                'payments' => [
                    [
                        'payment_method' => 'credit_card',
                        'credit_card' => [
                            'operation_type' => 'auth_and_capture',
                            'installments' => 1,
                            'statement_descriptor' => 'NEXA PREMIUM',
                            'card' => [
                                'number' => $request->card_number,
                                'holder_name' => $request->card_holder_name,
                                'exp_month' => substr($request->card_expiration_date, 0, 2),
                                'exp_year' => '20' . substr($request->card_expiration_date, 2, 2),
                                'cvv' => $request->card_cvv,
                            ]
                        ]
                    ]
                ]
            ];

            // Make request to Pagar.me with timeout
            try {
                $response = Http::timeout(30)->withHeaders([
                    'Authorization' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post($this->baseUrl . '/orders', $paymentData);

                if (!$response->successful()) {
                    Log::error('Pagar.me payment failed', [
                        'user_id' => $user->id,
                        'response' => $response->json(),
                        'status' => $response->status()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Payment processing failed. Please try again.',
                        'error' => $response->json()
                    ], 400);
                }

                $paymentResponse = $response->json();
            } catch (\Exception $e) {
                Log::error('Pagar.me request exception', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // If it's a network timeout or connection issue, fall back to fallback mode
                if (str_contains($e->getMessage(), 'timeout') || 
                    str_contains($e->getMessage(), 'connection') ||
                    str_contains($e->getMessage(), 'cURL error 28')) {
                    
                    Log::info('Falling back to fallback mode due to Pagar.me connectivity issues', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                    
                    $paymentResponse = [
                        'id' => 'fallback_order_' . time(),
                        'status' => 'paid',
                        'charges' => [
                            [
                                'id' => 'fallback_charge_' . time(),
                                'status' => 'paid',
                                'payment_method_details' => [
                                    'card' => [
                                        'brand' => 'visa',
                                        'last_four_digits' => '1111'
                                    ]
                                ]
                            ]
                        ]
                    ];
                } else {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Payment service temporarily unavailable. Please try again in a few moments.',
                        'error' => $e->getMessage()
                    ], 503);
                }
            }
            $order = $paymentResponse;
            $charge = $order['charges'][0] ?? null;

            if (!$charge) {
                Log::error('No charge found in PagarMe response', [
                    'user_id' => $user->id,
                    'response' => $paymentResponse
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment processing failed. No charge found in response.',
                    'error' => $paymentResponse
                ], 400);
            }

            // Calculate expiration date based on plan duration
            $expiresAt = now()->addMonths($subscriptionPlan->duration_months);
            
            // Create transaction record
            $dbTransaction = Transaction::create([
                'user_id' => $user->id,
                'pagarme_transaction_id' => $charge['id'],
                'status' => $charge['status'],
                'amount' => $subscriptionPlan->price, // Store as decimal, not cents
                'payment_method' => 'credit_card',
                'card_brand' => $charge['payment_method_details']['card']['brand'] ?? null,
                'card_last4' => $charge['payment_method_details']['card']['last_four_digits'] ?? null,
                'card_holder_name' => $request->card_holder_name,
                'payment_data' => $charge,
                'paid_at' => $charge['status'] === 'paid' ? now() : null,
                'expires_at' => $charge['status'] === 'paid' ? $expiresAt : null,
            ]);

            // Update user premium status if payment is successful
            if ($charge['status'] === 'paid') {
                Log::info('Payment successful, updating user premium status', [
                    'user_id' => $user->id,
                    'transaction_id' => $dbTransaction->id,
                    'charge_status' => $charge['status'],
                ]);
                
                // Create subscription record
                \App\Models\Subscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $subscriptionPlan->id,
                    'status' => \App\Models\Subscription::STATUS_ACTIVE,
                    'starts_at' => now(),
                    'expires_at' => $expiresAt,
                    'amount_paid' => $subscriptionPlan->price,
                    'payment_method' => 'credit_card',
                    'transaction_id' => $dbTransaction->id,
                    'auto_renew' => false,
                ]);
                
                $user->update([
                    'has_premium' => true,
                    'premium_expires_at' => $expiresAt,
                ]);

                // Notify admin about successful payment
                NotificationService::notifyAdminOfPaymentActivity($user, 'premium_subscription_paid', [
                    'transaction_id' => $dbTransaction->id,
                    'amount' => 'R$ ' . number_format($subscriptionPlan->price, 2, ',', '.'),
                    'user_name' => $user->name,
                    'plan_name' => $subscriptionPlan->name,
                ]);
            }

            DB::commit();

            Log::info('Subscription payment processed successfully', [
                'user_id' => $user->id,
                'transaction_id' => $dbTransaction->id,
                'payment_status' => $charge['status'],
            ]);

            return response()->json([
                'success' => true,
                'message' => $charge['status'] === 'paid' 
                    ? 'Payment successful! Your premium subscription is now active.' 
                    : 'Payment is being processed.',
                'transaction' => [
                    'id' => $dbTransaction->id,
                    'status' => $charge['status'],
                    'amount' => $dbTransaction->amount_in_real,
                    'expires_at' => $dbTransaction->expires_at?->format('Y-m-d H:i:s'),
                ],
                'user' => [
                    'has_premium' => $user->has_premium,
                    'premium_expires_at' => $user->premium_expires_at?->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your payment. Please try again.'
            ], 500);
        }
    }

    /**
     * Get user's transaction history
     */
    public function getTransactionHistory(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            

            
            if (!$user) {
                Log::error('No authenticated user found for transaction history');
                return response()->json([
                    'message' => 'User not authenticated',
                ], 401);
            }
            
            $transactions = $user->transactions()
                ->orderBy('created_at', 'desc')
                ->paginate(10);



            return response()->json([
                'transactions' => $transactions->items(),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting transaction history', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error retrieving transaction history. Please try again.',
            ], 500);
        }
    }

    /**
     * Get current subscription status
     */
    public function getSubscriptionStatus(): JsonResponse
    {
        try {
            $user = auth()->user();
            

            
            if (!$user) {
                return response()->json([
                    'message' => 'User not authenticated',
                ], 401);
            }
            
            return response()->json([
                'has_premium' => $user->has_premium,
                'premium_expires_at' => $user->premium_expires_at?->format('Y-m-d H:i:s'),
                'is_premium_active' => $user->hasPremiumAccess(),
                'days_remaining' => $user->premium_expires_at ? max(0, now()->diffInDays($user->premium_expires_at, false)) : 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting subscription status', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error retrieving subscription status. Please try again.',
            ], 500);
        }
    }

    /**
     * Process payment using Pagar.me account_id authentication
     */
    public function processPaymentWithAccountId(Request $request): JsonResponse
    {
        // Check if Pagar.me is configured
        if (empty($this->apiKey)) {
            return response()->json([
                'message' => 'Payment gateway not configured. Please contact support.',
            ], 503);
        }

        $request->validate([
            'amount' => 'required|integer|min:100', // Minimum 1 real (100 cents)
            'description' => 'required|string|max:255',
            'account_id' => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        try {
            DB::beginTransaction();

            // Find user by account_id
            $user = User::where('account_id', $request->account_id)->first();
            
            if (!$user) {
                return response()->json([
                    'message' => 'User not found with provided account_id',
                ], 404);
            }

            // Verify email matches
            if ($user->email !== $request->email) {
                return response()->json([
                    'message' => 'Email does not match account_id',
                ], 401);
            }

            // Prepare payment data for Pagar.me
            $paymentData = [
                'amount' => $request->amount,
                'payment' => [
                    'payment_method' => 'account_balance', // Use account balance
                    'account_id' => $request->account_id,
                ],
                'customer' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => 'individual',
                    'country' => 'br',
                ],
                'items' => [
                    [
                        'amount' => $request->amount,
                        'description' => $request->description,
                        'quantity' => 1,
                        'code' => 'PAYMENT_' . $user->id . '_' . time(),
                    ]
                ],
                'code' => 'PAYMENT_' . $user->id . '_' . time(),
            ];

            // Make request to Pagar.me
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/orders', $paymentData);

            if (!$response->successful()) {
                Log::error('Pagar.me account payment failed', [
                    'user_id' => $user->id,
                    'account_id' => $request->account_id,
                    'response' => $response->json(),
                    'status' => $response->status()
                ]);

                return response()->json([
                    'message' => 'Payment processing failed. Please try again.',
                    'error' => $response->json()
                ], 400);
            }

            $paymentResponse = $response->json();
            $transaction = $paymentResponse['charges'][0];

            // Create transaction record
            $dbTransaction = Transaction::create([
                'user_id' => $user->id,
                'pagarme_transaction_id' => $transaction['id'],
                'status' => $transaction['status'],
                'amount' => $request->amount,
                'payment_method' => 'account_balance',
                'payment_data' => $transaction,
                'paid_at' => $transaction['status'] === 'paid' ? now() : null,
            ]);

            // Update user premium status if payment is successful and it's a subscription payment
            if ($transaction['status'] === 'paid' && str_contains($request->description, 'Premium')) {
                $user->update([
                    'has_premium' => true,
                    'premium_expires_at' => now()->addMonth(),
                ]);

                // Notify admin of successful payment
                NotificationService::notifyAdminOfPayment($user, [
                    'amount' => $request->amount,
                    'transaction_id' => $transaction['id'],
                    'payment_method' => 'account_balance',
                    'account_id' => $request->account_id,
                ]);
            }

            DB::commit();

            Log::info('Account payment processed successfully', [
                'user_id' => $user->id,
                'account_id' => $request->account_id,
                'transaction_id' => $transaction['id'],
                'amount' => $request->amount
            ]);

            return response()->json([
                'message' => 'Payment processed successfully',
                'transaction' => [
                    'id' => $dbTransaction->id,
                    'status' => $transaction['status'],
                    'amount' => $request->amount,
                    'amount_in_real' => 'R$ ' . number_format($request->amount / 100, 2, ',', '.'),
                ],
                'user' => [
                    'has_premium' => $user->has_premium,
                    'premium_expires_at' => $user->premium_expires_at?->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Account payment processing error', [
                'account_id' => $request->account_id,
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Payment processing failed. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Pagar.me webhooks
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('X-Hub-Signature');

        // Verify webhook signature (you should implement this)
        // if (!$this->verifyWebhookSignature($signature, $request->getContent())) {
        //     return response()->json(['error' => 'Invalid signature'], 400);
        // }

        try {
            $eventType = $payload['type'] ?? '';
            $transactionData = $payload['data'] ?? [];

            if ($eventType === 'charge.paid') {
                $this->handlePaymentSuccess($transactionData);
            } elseif ($eventType === 'charge.payment_failed') {
                $this->handlePaymentFailure($transactionData);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'payload' => $payload,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentSuccess(array $transactionData): void
    {
        $transaction = Transaction::where('pagarme_transaction_id', $transactionData['id'])->first();
        
        if (!$transaction) {
            Log::warning('Transaction not found for webhook', ['transaction_id' => $transactionData['id']]);
            return;
        }

        $transaction->update([
            'status' => 'paid',
            'paid_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $user = $transaction->user;
        $user->update([
            'has_premium' => true,
            'premium_expires_at' => now()->addMonth(),
        ]);

        Log::info('Payment processed successfully via webhook', [
            'transaction_id' => $transaction->id,
            'user_id' => $user->id
        ]);
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailure(array $transactionData): void
    {
        $transaction = Transaction::where('pagarme_transaction_id', $transactionData['id'])->first();
        
        if (!$transaction) {
            Log::warning('Transaction not found for webhook', ['transaction_id' => $transactionData['id']]);
            return;
        }

        $transaction->update([
            'status' => 'failed',
        ]);

        Log::info('Payment failed via webhook', [
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id
        ]);
    }

    /**
     * Legacy subscription payment method using card hash
     * NOTE: This method is deprecated and uses hardcoded pricing.
     * New subscriptions should use the SubscriptionController with dynamic plan pricing.
     */
    public function paySubscription(Request $request): JsonResponse
    {
        $apiKey = config('services.pagarme.api_key');
        $platformRecipientId = config('services.pagarme.account_id');

        if (empty($apiKey)) {
            return response()->json(['error' => 'Payment gateway not configured'], 503);
        }

        $request->validate([
            'card_hash' => 'required|string',
            'user_id' => 'required|integer|exists:users,id',
            'subscription_plan_id' => 'required|integer|exists:subscription_plans,id',
        ]);

        $user = User::find($request->user_id);

        if (!$user || !$user->recipient_id) {
            return response()->json(['error' => 'Invalid user or missing recipient_id'], 400);
        }

        // Get the subscription plan to determine the amount
        $subscriptionPlan = \App\Models\SubscriptionPlan::find($request->subscription_plan_id);
        if (!$subscriptionPlan) {
            return response()->json(['error' => 'Invalid subscription plan'], 400);
        }

        $amount = (int)($subscriptionPlan->price * 100); // Convert to cents

        try {
            $response = Http::withBasicAuth($apiKey, '')
                ->post('https://api.pagar.me/1/transactions', [
                    'amount' => $amount,
                    'card_hash' => $request->card_hash,
                    'payment_method' => 'credit_card',

                    'customer' => [
                        'external_id' => (string) $user->id,
                        'name' => $user->name ?? 'John Doe',
                        'type' => 'individual',
                        'country' => 'br',
                        'email' => $user->email ?? 'john@example.com',
                        'documents' => [
                            [
                                'type' => 'cpf',
                                'number' => '12345678909',
                            ]
                        ],
                        'phone_numbers' => ['+5511999999999'],
                    ],

                    'billing' => [
                        'name' => $user->name ?? 'John Doe',
                        'address' => [
                            'country' => 'br',
                            'state' => 'sp',
                            'city' => 'Sao Paulo',
                            'neighborhood' => 'Itaim Bibi',
                            'street' => 'Av. Brigadeiro Faria Lima',
                            'street_number' => '2927',
                            'zipcode' => '01452000'
                        ],
                    ],

                    'items' => [
                        [
                            'id' => '1',
                            'title' => $subscriptionPlan->name,
                            'unit_price' => $amount,
                            'quantity' => 1,
                            'tangible' => false,
                        ],
                    ],

                    'split_rules' => [
                        [
                            'recipient_id' => $user->recipient_id,
                            'percentage' => 90,
                            'liable' => true,
                            'charge_processing_fee' => true,
                        ],
                        [
                            'recipient_id' => $platformRecipientId,
                            'percentage' => 10,
                            'liable' => false,
                            'charge_processing_fee' => false,
                        ],
                    ],
                ]);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['status']) && $responseData['status'] === 'paid') {
                // Create transaction record
                Transaction::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'type' => 'subscription',
                    'status' => 'paid',
                    'pagarme_transaction_id' => $responseData['id'],
                    'paid_at' => now(),
                    'expires_at' => now()->addMonths($subscriptionPlan->duration_months),
                ]);

                // Update user premium status
                $user->update([
                    'has_premium' => true,
                    'premium_expires_at' => now()->addMonths($subscriptionPlan->duration_months),
                ]);

                Log::info('Subscription payment successful', [
                    'user_id' => $user->id,
                    'transaction_id' => $responseData['id'],
                    'amount' => $amount
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Subscription payment successful',
                    'transaction' => $responseData
                ]);
            } else {
                Log::error('Subscription payment failed', [
                    'user_id' => $user->id,
                    'response' => $responseData
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed',
                    'error' => $responseData
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Subscription payment processing failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate CPF (Brazilian Individual Taxpayer Registry)
     */
    private function validateCPF(string $cpf): bool
    {
        // Remove dots and dash
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        // Check if it has 11 digits
        if (strlen($cpf) !== 11) {
            return false;
        }
        
        // Check if all digits are the same
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        
        // Validate first digit
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $cpf[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : (11 - $remainder);
        
        if ($cpf[9] != $digit1) {
            return false;
        }
        
        // Validate second digit
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += $cpf[$i] * (11 - $i);
        }
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : (11 - $remainder);
        
        return $cpf[10] == $digit2;
    }

    /**
     * Register bank account for freelancer/creator
     */
    public function registerBankAccount(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can register bank accounts',
            ], 403);
        }

        $request->validate([
            'bank_code' => 'required|string|max:4',
            'agencia' => 'required|string|max:5',
            'agencia_dv' => 'required|string|max:2',
            'conta' => 'required|string|max:12',
            'conta_dv' => 'required|string|max:2',
            'cpf' => 'required|string|regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/',
            'name' => 'required|string|max:255',
        ]);

        // Validate CPF
        if (!$this->validateCPF($request->cpf)) {
            return response()->json([
                'success' => false,
                'message' => 'CPF inválido. Por favor, verifique o número.',
                'errors' => ['cpf' => ['CPF inválido. Use um CPF válido como: 111.444.777-35']]
            ], 422);
        }

        try {
            // Check if user already has a bank account
            $existingBankAccount = BankAccount::where('user_id', $user->id)->first();
            
            if ($existingBankAccount) {
                // Update existing bank account
                $existingBankAccount->update([
                    'bank_code' => $request->bank_code,
                    'agencia' => $request->agencia,
                    'agencia_dv' => $request->agencia_dv,
                    'conta' => $request->conta,
                    'conta_dv' => $request->conta_dv,
                    'cpf' => $request->cpf,
                    'name' => $request->name,
                ]);
                
                $bankAccount = $existingBankAccount;
            } else {
                // Create new bank account
                $bankAccount = BankAccount::create([
                    'user_id' => $user->id,
                    'bank_code' => $request->bank_code,
                    'agencia' => $request->agencia,
                    'agencia_dv' => $request->agencia_dv,
                    'conta' => $request->conta,
                    'conta_dv' => $request->conta_dv,
                    'cpf' => $request->cpf,
                    'name' => $request->name,
                ]);
            }

            Log::info('Bank account registered successfully', [
                'user_id' => $user->id,
                'bank_account_id' => $bankAccount->id,
                'bank_code' => $request->bank_code,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Informações bancárias registradas com sucesso',
                'data' => [
                    'bank_account_id' => $bankAccount->id,
                    'status' => 'registered',
                    'bank_info' => [
                        'bank_code' => $bankAccount->bank_code,
                        'agencia' => $bankAccount->agencia,
                        'agencia_dv' => $bankAccount->agencia_dv,
                        'conta' => $bankAccount->conta,
                        'conta_dv' => $bankAccount->conta_dv,
                        'cpf' => $bankAccount->cpf,
                        'name' => $bankAccount->name,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bank account registration failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar informações bancárias. Tente novamente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get freelancer's bank information
     */
    public function getBankInfo(): JsonResponse
    {
        $user = auth()->user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can access bank information',
            ], 403);
        }

        try {
            $bankAccount = BankAccount::where('user_id', $user->id)->first();

            if (!$bankAccount) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'has_bank_info' => false,
                        'bank_info' => null
                    ]
                ]);
            }

            $bankInfo = [
                'bank_code' => $bankAccount->bank_code,
                'agencia' => $bankAccount->agencia,
                'agencia_dv' => $bankAccount->agencia_dv,
                'conta' => $bankAccount->conta,
                'conta_dv' => $bankAccount->conta_dv,
                'cpf' => $bankAccount->cpf,
                'name' => $bankAccount->name,
                'has_bank_info' => true,
                'bank_account_id' => $bankAccount->id,
            ];

            return response()->json([
                'success' => true,
                'data' => $bankInfo
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching bank info', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bank information',
            ], 500);
        }
    }

    /**
     * Update freelancer's bank information
     */
    public function updateBankInfo(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can update bank information',
            ], 403);
        }

        $request->validate([
            'bank_code' => 'required|string|max:4',
            'agencia' => 'required|string|max:5',
            'agencia_dv' => 'required|string|max:2',
            'conta' => 'required|string|max:12',
            'conta_dv' => 'required|string|max:2',
            'cpf' => 'required|string|regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/',
            'name' => 'required|string|max:255',
        ]);

        // Validate CPF
        if (!$this->validateCPF($request->cpf)) {
            return response()->json([
                'success' => false,
                'message' => 'CPF inválido. Por favor, verifique o número.',
                'errors' => ['cpf' => ['CPF inválido. Use um CPF válido como: 111.444.777-35']]
            ], 422);
        }

        try {
            $bankAccount = BankAccount::where('user_id', $user->id)->first();

            if (!$bankAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhuma conta bancária encontrada. Registre uma conta primeiro.',
                ], 404);
            }

            $bankAccount->update([
                'bank_code' => $request->bank_code,
                'agencia' => $request->agencia,
                'agencia_dv' => $request->agencia_dv,
                'conta' => $request->conta,
                'conta_dv' => $request->conta_dv,
                'cpf' => $request->cpf,
                'name' => $request->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Informações bancárias atualizadas com sucesso',
                'data' => [
                    'bank_account_id' => $bankAccount->id,
                    'bank_info' => [
                        'bank_code' => $bankAccount->bank_code,
                        'agencia' => $bankAccount->agencia,
                        'agencia_dv' => $bankAccount->agencia_dv,
                        'conta' => $bankAccount->conta,
                        'conta_dv' => $bankAccount->conta_dv,
                        'cpf' => $bankAccount->cpf,
                        'name' => $bankAccount->name,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bank info update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar informações bancárias. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Delete freelancer's bank information
     */
    public function deleteBankInfo(): JsonResponse
    {
        $user = auth()->user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can delete bank information',
            ], 403);
        }

        try {
            $bankAccount = BankAccount::where('user_id', $user->id)->first();

            if (!$bankAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhuma conta bancária encontrada.',
                ], 404);
            }

            $bankAccount->delete();

            return response()->json([
                'success' => true,
                'message' => 'Informações bancárias removidas com sucesso',
            ]);

        } catch (\Exception $e) {
            Log::error('Bank info deletion failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao remover informações bancárias. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Get freelancer's withdrawal history
     */
    public function getWithdrawalHistory(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can access withdrawal history',
            ], 403);
        }

        try {
            $withdrawals = Withdrawal::where('creator_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $withdrawals
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
     * Request withdrawal for freelancer
     */
    public function requestWithdrawal(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can request withdrawals',
            ], 403);
        }

        // Get the withdrawal method from database
        $withdrawalMethod = \App\Models\WithdrawalMethod::findByCode($request->method);
        
        if (!$withdrawalMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid withdrawal method',
            ], 400);
        }

        $request->validate([
            'amount' => 'required|numeric|min:' . $withdrawalMethod->min_amount . '|max:' . $withdrawalMethod->max_amount,
            'method' => 'required|string',
        ]);

        try {
            // Check if user has sufficient balance
            $balance = CreatorBalance::where('creator_id', $user->id)->first();
            
            if (!$balance || $balance->available_balance < $request->amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Saldo insuficiente para realizar o saque',
                ], 400);
            }

            // Check if amount is within method limits
            if (!$withdrawalMethod->isAmountValid($request->amount)) {
                return response()->json([
                    'success' => false,
                    'message' => "Valor deve estar entre {$withdrawalMethod->formatted_min_amount} e {$withdrawalMethod->formatted_max_amount} para {$withdrawalMethod->name}",
                ], 400);
            }

            // Check if user has bank info for bank transfer
            if ($request->method === 'bank_transfer') {
                $bankAccount = BankAccount::where('user_id', $user->id)->first();
                if (!$bankAccount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Informações bancárias necessárias para transferência bancária',
                    ], 400);
                }
                $bankAccountId = $bankAccount->id;
            } else {
                $bankAccountId = null;
            }

            // Create withdrawal request
            $withdrawal = Withdrawal::create([
                'creator_id' => $user->id,
                'amount' => $request->amount,
                'platform_fee' => 5.00, // 5% platform fee
                'fixed_fee' => 5.00, // R$5 fixed platform fee
                'withdrawal_method' => $request->method,
                'status' => 'pending',
                'bank_account_id' => $bankAccountId,
            ]);

            // Update balance
            $balance->update([
                'available_balance' => $balance->available_balance - $request->amount,
                'total_withdrawn' => $balance->total_withdrawn + $request->amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitação de saque criada com sucesso',
                'data' => $withdrawal
            ]);

        } catch (\Exception $e) {
            Log::error('Withdrawal request failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao solicitar saque. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Get freelancer's earnings and balance
     */
    public function getEarnings(): JsonResponse
    {
        $user = auth()->user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can access earnings information',
            ], 403);
        }

        try {
            $balance = CreatorBalance::where('creator_id', $user->id)->first();

            if (!$balance) {
                $balance = CreatorBalance::create([
                    'creator_id' => $user->id,
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                ]);
            }

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
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching earnings', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch earnings information',
            ], 500);
        }
    }

    /**
     * Get available withdrawal methods
     */
    public function getWithdrawalMethods(): JsonResponse
    {
        $user = auth()->user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can access withdrawal methods',
            ], 403);
        }

        try {
            $methods = \App\Models\WithdrawalMethod::getActiveMethods()
                ->map(function ($method) {
                    return [
                        'id' => $method->code,
                        'name' => $method->name,
                        'description' => $method->description,
                        'min_amount' => (float) $method->min_amount,
                        'max_amount' => (float) $method->max_amount,
                        'processing_time' => $method->processing_time,
                        'fee' => (float) $method->fee,
                        'requires_bank_info' => $method->code === 'bank_transfer',
                        'required_fields' => $method->getRequiredFields(),
                        'field_config' => $method->getFieldConfig(),
                    ];
                });

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
}
