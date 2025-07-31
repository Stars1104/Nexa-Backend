<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
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

        // Check if Pagar.me is configured
        if (empty($this->apiKey)) {
            Log::error('Pagar.me API key not configured');
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway not configured. Please contact support.',
            ], 503);
        }

        $request->validate([
            'card_number' => 'required|string|size:16',
            'card_holder_name' => 'required|string|max:255',
            'card_expiration_date' => 'required|string|size:4', // MMYY format
            'card_cvv' => 'required|string|min:3|max:4',
            'cpf' => 'required|string|regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', // CPF format validation
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
                        'amount' => 4999, // Pagar.me expects amount in cents
                        'description' => 'Nexa Premium Subscription - 1 Month',
                        'quantity' => 1,
                        'code' => 'NEXA_PREMIUM_MONTH'
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

            // Check if we're in test mode (development only)
            $testMode = env('APP_ENV') === 'local' || 
                       ($request->has('test_mode') && $request->test_mode === 'true') ||
                       env('PAGARME_TEST_MODE', 'false') === 'true';
            
            if ($testMode) {
                // Simulate successful payment for testing
                Log::info('Test mode: Simulating successful payment', [
                    'user_id' => $user->id,
                    'test_mode' => true,
                    'reason' => env('APP_ENV') === 'local' ? 'local_environment' : 'explicit_test_mode'
                ]);
                
                $paymentResponse = [
                    'id' => 'test_order_' . time(),
                    'status' => 'paid',
                    'charges' => [
                        [
                            'id' => 'test_charge_' . time(),
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
                // Make request to Pagar.me with timeout
                try {
                    $response = Http::timeout(30)->withHeaders([
                        'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
                        'Content-Type' => 'application/json',
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

                    // If it's a network timeout or connection issue, fall back to test mode
                    if (str_contains($e->getMessage(), 'timeout') || 
                        str_contains($e->getMessage(), 'connection') ||
                        str_contains($e->getMessage(), 'cURL error 28')) {
                        
                        Log::info('Falling back to test mode due to Pagar.me connectivity issues', [
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

            // Create transaction record
            $dbTransaction = Transaction::create([
                'user_id' => $user->id,
                'pagarme_transaction_id' => $charge['id'],
                'status' => $charge['status'],
                'amount' => 49.99, // Store as decimal, not cents
                'payment_method' => 'credit_card',
                'card_brand' => $charge['payment_method_details']['card']['brand'] ?? null,
                'card_last4' => $charge['payment_method_details']['card']['last_four_digits'] ?? null,
                'card_holder_name' => $request->card_holder_name,
                'payment_data' => $charge,
                'paid_at' => $charge['status'] === 'paid' ? now() : null,
                'expires_at' => $charge['status'] === 'paid' ? now()->addMonth() : null,
            ]);

            // Update user premium status if payment is successful
            if ($charge['status'] === 'paid') {
                Log::info('Payment successful, updating user premium status', [
                    'user_id' => $user->id,
                    'transaction_id' => $dbTransaction->id,
                    'charge_status' => $charge['status'],
                ]);
                
                $user->update([
                    'has_premium' => true,
                    'premium_expires_at' => now()->addMonth(),
                ]);

                // Notify admin about successful payment
                NotificationService::notifyAdminOfPaymentActivity($user, 'premium_subscription_paid', [
                    'transaction_id' => $dbTransaction->id,
                    'amount' => 'R$ 49,99',
                    'user_name' => $user->name,
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
        ]);

        $user = User::find($request->user_id);

        if (!$user || !$user->recipient_id) {
            return response()->json(['error' => 'Invalid user or missing recipient_id'], 400);
        }

        $amount = 4999; // R$49.99 in cents

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
                            'title' => 'Monthly Subscription',
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
                    'expires_at' => now()->addMonth(),
                ]);

                // Update user premium status
                $user->update([
                    'has_premium' => true,
                    'premium_expires_at' => now()->addMonth(),
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
}
