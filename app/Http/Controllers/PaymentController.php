<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    private $pagarMeApiKey;
    private $pagarMeAccountId;
    private $pagarMeBaseUrl = 'https://api.pagar.me/core/v5';

    public function __construct()
    {
        $this->pagarMeApiKey = config('services.pagarme.secret_key');
        $this->pagarMeAccountId = config('services.pagarme.account_id');
    }

    /**
     * Get user's payment methods
     */
    public function getPaymentMethods(): JsonResponse
    {
        $user = Auth::user();
        
        try {
            // First, try to create or get the customer
            $customer = $this->createOrGetCustomer($user);
            
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                'Content-Type' => 'application/json',
            ])->get("{$this->pagarMeBaseUrl}/customers/{$customer['id']}/cards");

            if ($response->successful()) {
                $cards = $response->json()['data'] ?? [];
                
                // Transform Pagar.me cards to our format
                $paymentMethods = collect($cards)->map(function ($card) {
                    return [
                        'id' => $card['id'],
                        'type' => 'Cartão de Crédito',
                        'last4' => $card['last_four_digits'],
                        'expires' => $card['exp_month'] . '/' . $card['exp_year'],
                        'isDefault' => $card['type'] === 'credit',
                        'brand' => $card['brand'],
                        'holder_name' => $card['holder_name'],
                    ];
                })->toArray();

                return response()->json([
                    'success' => true,
                    'data' => $paymentMethods
                ]);
            }

            // If the request failed, return empty array instead of error
            Log::warning('Failed to fetch cards for customer: ' . $customer['id'] . '. Response: ' . $response->body());
            
            return response()->json([
                'success' => true,
                'data' => []
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching payment methods: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a new payment method
     */
    public function createPaymentMethod(Request $request): JsonResponse
    {
        // Log the incoming request data for debugging
        Log::info('Payment method creation request', [
            'user_id' => Auth::id(),
            'request_data' => $request->all(),
            'headers' => $request->headers->all()
        ]);

        $validator = Validator::make($request->all(), [
            'card_number' => 'required|string|size:16',
            'holder_name' => 'required|string|max:255',
            'exp_month' => 'required|integer|between:1,12',
            'exp_year' => 'required|integer|min:' . date('Y'),
            'cvv' => 'required|string|size:3',
            'isDefault' => 'boolean'
        ]);

        if ($validator->fails()) {
            Log::error('Payment method validation failed', [
                'user_id' => Auth::id(),
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        try {
            // First, create or get customer
            $customer = $this->createOrGetCustomer($user);

            // Create card
            $cardData = [
                'type' => 'credit',
                'number' => $request->card_number,
                'holder_name' => $request->holder_name,
                'exp_month' => $request->exp_month,
                'exp_year' => $request->exp_year,
                'cvv' => $request->cvv,
                'billing_address' => [
                    'line_1' => 'Rua Teste',
                    'line_2' => 'Apto 123',
                    'zip_code' => '01234-567',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'country' => 'BR'
                ]
            ];

            Log::info('Sending card data to Pagar.me', [
                'user_id' => Auth::id(),
                'customer_id' => $customer['id'],
                'card_data' => array_merge($cardData, ['number' => '****' . substr($request->card_number, -4)])
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                'Content-Type' => 'application/json',
            ])->post("{$this->pagarMeBaseUrl}/customers/{$customer['id']}/cards", $cardData);

            if ($response->successful()) {
                $card = $response->json();
                
                $paymentMethod = [
                    'id' => $card['id'],
                    'type' => 'Cartão de Crédito',
                    'last4' => $card['last_four_digits'],
                    'expires' => $card['exp_month'] . '/' . $card['exp_year'],
                    'isDefault' => $request->isDefault ?? false,
                    'brand' => $card['brand'],
                    'holder_name' => $card['holder_name'],
                ];

                Log::info('Payment method created successfully', [
                    'user_id' => Auth::id(),
                    'card_id' => $card['id']
                ]);

                // Notify admin of payment method creation
                \App\Services\NotificationService::notifyAdminOfPaymentActivity($user, 'payment_method_created', [
                    'card_id' => $card['id'],
                    'card_brand' => $card['brand'],
                    'last_four_digits' => $card['last_four_digits'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment method created successfully',
                    'data' => $paymentMethod
                ]);
            }

            $error = $response->json();
            Log::error('Pagar.me card creation failed: ' . json_encode($error));
            
            return response()->json([
                'success' => false,
                'message' => $error['message'] ?? 'Failed to create payment method',
                'details' => $error
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error creating payment method: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete a payment method
     */
    public function deletePaymentMethod(Request $request, $cardId): JsonResponse
    {
        $user = Auth::user();

        try {
            // Get the customer first
            $customer = $this->createOrGetCustomer($user);
            
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                'Content-Type' => 'application/json',
            ])->delete("{$this->pagarMeBaseUrl}/customers/{$customer['id']}/cards/{$cardId}");

            if ($response->successful()) {
                // Notify admin of payment method deletion
                \App\Services\NotificationService::notifyAdminOfPaymentActivity($user, 'payment_method_deleted', [
                    'card_id' => $cardId,
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment method deleted successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment method'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error deleting payment method: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Process a payment
     */
    public function processPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'card_id' => 'required|string',
            'description' => 'required|string|max:255',
            'campaign_id' => 'required|exists:campaigns,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        try {
            // Get the customer first
            $customer = $this->createOrGetCustomer($user);
            
            $paymentData = [
                'amount' => (int)($request->amount * 100), // Convert to cents
                'payment' => [
                    'payment_method' => 'credit_card',
                    'credit_card' => [
                        'operation_type' => 'auth_and_capture',
                        'installments' => 1,
                        'statement_descriptor' => 'NEXA',
                        'card_id' => $request->card_id
                    ]
                ],
                'items' => [
                    [
                        'amount' => (int)($request->amount * 100),
                        'description' => $request->description,
                        'quantity' => 1,
                        'code' => 'CAMPAIGN_' . $request->campaign_id
                    ]
                ],
                'customer' => [
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'email' => $customer['email'],
                    'type' => 'individual',
                    'document' => '12345678909'
                ],
                'code' => 'PAYMENT_' . time(),
                'metadata' => [
                    'campaign_id' => $request->campaign_id,
                    'user_id' => $user->id
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                'Content-Type' => 'application/json',
            ])->post("{$this->pagarMeBaseUrl}/orders", $paymentData);

            if ($response->successful()) {
                $payment = $response->json();
                
                // Notify admin of payment processing
                \App\Services\NotificationService::notifyAdminOfPaymentActivity($user, 'payment_processed', [
                    'payment_id' => $payment['id'],
                    'amount' => $payment['amount'] / 100,
                    'status' => $payment['status'],
                    'campaign_id' => $request->campaign_id,
                    'description' => $request->description,
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'data' => [
                        'id' => $payment['id'],
                        'status' => $payment['status'],
                        'amount' => $payment['amount'] / 100,
                        'created_at' => $payment['created_at']
                    ]
                ]);
            }

            $error = $response->json();
            Log::error('Pagar.me payment failed: ' . json_encode($error));
            
            return response()->json([
                'success' => false,
                'message' => $error['errors'][0]['message'] ?? 'Payment failed'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error processing payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get payment history
     */
    public function getPaymentHistory(Request $request): JsonResponse
    {
        $user = Auth::user();
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 10);

        try {
            // Get the customer first
            $customer = $this->createOrGetCustomer($user);
            
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                'Content-Type' => 'application/json',
            ])->get("{$this->pagarMeBaseUrl}/orders", [
                'customer_id' => $customer['id'],
                'page' => $page,
                'size' => $perPage
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $payments = collect($data['data'] ?? [])->map(function ($order) {
                    return [
                        'id' => $order['id'],
                        'amount' => $order['amount'] / 100,
                        'status' => $order['status'],
                        'created_at' => $order['created_at'],
                        'description' => $order['items'][0]['description'] ?? 'Payment',
                        'code' => $order['code']
                    ];
                })->toArray();

                return response()->json([
                    'success' => true,
                    'data' => $payments,
                    'pagination' => [
                        'current_page' => $data['paging']['current_page'] ?? 1,
                        'total_pages' => $data['paging']['total_pages'] ?? 1,
                        'total_items' => $data['paging']['total'] ?? 0
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment history'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error fetching payment history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create or get customer in Pagar.me
     */
    private function createOrGetCustomer($user)
    {
        try {
            // Try to get existing customer by ID
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                'Content-Type' => 'application/json',
            ])->get("{$this->pagarMeBaseUrl}/customers/{$user->id}");

            if ($response->successful()) {
                return $response->json();
            }

            // If customer doesn't exist, try to find by email
            $searchResponse = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                'Content-Type' => 'application/json',
            ])->get("{$this->pagarMeBaseUrl}/customers", [
                'email' => $user->email
            ]);

            if ($searchResponse->successful()) {
                $customers = $searchResponse->json()['data'] ?? [];
                if (!empty($customers)) {
                    return $customers[0]; // Return the first customer with this email
                }
            }

            // Create new customer without specifying ID (let Pagar.me generate it)
            $customerData = [
                'name' => $user->name,
                'email' => $user->email,
                'type' => 'individual',
                'document' => '12345678909', // Default document
                'phones' => [
                    'mobile_phone' => [
                        'country_code' => '55',
                        'area_code' => '11',
                        'number' => '999999999'
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                'Content-Type' => 'application/json',
            ])->post("{$this->pagarMeBaseUrl}/customers", $customerData);

            if ($response->successful()) {
                $customer = $response->json();
                Log::info('Created new Pagar.me customer', [
                    'user_id' => $user->id,
                    'pagarme_customer_id' => $customer['id'],
                    'email' => $user->email
                ]);
                return $customer;
            }

            $error = $response->json();
            Log::error('Failed to create Pagar.me customer', [
                'user_id' => $user->id,
                'error' => $error
            ]);
            throw new \Exception('Failed to create customer: ' . ($error['errors'][0]['message'] ?? 'Unknown error'));

        } catch (\Exception $e) {
            Log::error('Error creating/getting customer: ' . $e->getMessage());
            throw $e;
        }
    }
} 