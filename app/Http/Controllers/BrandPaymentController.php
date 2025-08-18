<?php

namespace App\Http\Controllers;

use App\Models\BrandPaymentMethod;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BrandPaymentController extends Controller
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('PAGARME_API_KEY', '');
        $this->baseUrl = 'https://api.pagar.me/core/v5';
    }

    /**
     * Save brand's payment method (card registration only, no payment)
     */
    public function savePaymentMethod(Request $request): JsonResponse
    {
        $user = auth()->user();

        Log::info('Save payment method request', [
            'user_id' => $user->id,
            'request_data' => $request->all()
        ]);

        // Check if user is a brand
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can save payment methods',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'card_hash' => 'required|string',
            'card_holder_name' => 'required|string|max:255',
            'cpf' => 'required|string|regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Real Pagar.me integration - check if API key is configured
            if (empty($this->apiKey)) {
                Log::error('Pagar.me API key not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'Payment gateway not configured. Please contact support.',
                ], 503);
            }

            // Validate API key format - Pagar.me keys start with 'sk_'
            if (!preg_match('/^sk_[a-zA-Z0-9]+$/', $this->apiKey)) {
                Log::error('Invalid Pagar.me API key format', ['key' => substr($this->apiKey, 0, 10) . '...']);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment gateway configuration. Please check your API key.',
                ], 503);
            }

            // Step 1: Create or get customer in Pagar.me
            $customerData = [
                'name' => $user->name,
                'email' => $user->email,
                'type' => 'individual',
                'country' => 'br',
                'documents' => [
                    [
                        'type' => 'cpf',
                        'number' => preg_replace('/[^0-9]/', '', $request->cpf)
                    ]
                ],
                'phones' => [
                    'mobile_phone' => [
                        'country_code' => '55',
                        'area_code' => '11',
                        'number' => '999999999'
                    ]
                ]
            ];

            // Check if customer already exists
            $existingCustomer = null;
            if ($user->defaultPaymentMethod) {
                $existingCustomer = $this->getCustomerFromPagarMe($user->defaultPaymentMethod->pagarme_customer_id);
            }

            if (!$existingCustomer) {
                // Create new customer
                $customerResponse = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl . '/customers', $customerData);

                if (!$customerResponse->successful()) {
                    $errorResponse = $customerResponse->json();
                    Log::error('Failed to create customer in Pagar.me', [
                        'user_id' => $user->id,
                        'response' => $errorResponse,
                        'status' => $customerResponse->status()
                    ]);

                    // Handle specific Pagar.me errors
                    if ($customerResponse->status() === 401) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment gateway authentication failed. Please check your API key configuration.',
                            'error' => 'Invalid API key or authentication failed'
                        ], 400);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to create customer account. Please try again.',
                        'error' => $errorResponse
                    ], 400);
                }

                $customer = $customerResponse->json();
            } else {
                $customer = $existingCustomer;
            }

            // Step 2: Create card using card_hash (Pagar.me v5 approach)
            $testTransactionData = [
                'amount' => 1, // 1 cent test transaction
                'payment' => [
                    'payment_method' => 'credit_card',
                    'credit_card' => [
                        'operation_type' => 'auth_only', // Only authorize, don't capture
                        'card_hash' => $request->card_hash,
                        'card' => [
                            'holder_name' => $request->card_holder_name,
                            'billing_address' => [
                                'street' => 'Av. Brigadeiro Faria Lima',
                                'number' => '2927',
                                'zip_code' => '01234-000',
                                'neighborhood' => 'Itaim Bibi',
                                'city' => 'São Paulo',
                                'state' => 'SP',
                                'country' => 'BR'
                            ]
                        ]
                    ]
                ],
                'customer_id' => $customer['id']
            ];

            // Make the test transaction to validate the card
            $testResponse = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/orders', $testTransactionData);

            if (!$testResponse->successful()) {
                $errorResponse = $testResponse->json();
                Log::error('Card validation failed', [
                    'user_id' => $user->id,
                    'response' => $errorResponse,
                    'status' => $testResponse->status()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Card validation failed. Please check your card details and try again.',
                    'error' => $errorResponse
                ], 400);
            }

            $testOrder = $testResponse->json();
            $cardInfo = [
                'id' => $testOrder['charges'][0]['payment_method_details']['card']['id'] ?? null,
                'brand' => $testOrder['charges'][0]['payment_method_details']['card']['brand'] ?? 'unknown',
                'last_four_digits' => $testOrder['charges'][0]['payment_method_details']['card']['last_four_digits'] ?? '****',
                'last4' => $testOrder['charges'][0]['payment_method_details']['card']['last_four_digits'] ?? '****',
                'holder_name' => $request->card_holder_name
            ];

            // Step 3: Save payment method to database
            $paymentMethod = BrandPaymentMethod::create([
                'user_id' => $user->id,
                'card_holder_name' => $request->card_holder_name,
                'card_brand' => $cardInfo['brand'],
                'card_last4' => $cardInfo['last4'],
                'pagarme_customer_id' => $customer['id'],
                'pagarme_card_id' => $cardInfo['id'],
                'is_default' => $request->is_default ?? false,
                'card_hash' => $request->card_hash,
            ]);

            // If this is set as default, unset other default methods
            if ($request->is_default) {
                BrandPaymentMethod::where('user_id', $user->id)
                    ->where('id', '!=', $paymentMethod->id)
                    ->update(['is_default' => false]);
            }

            Log::info('Payment method created successfully', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethod->id,
                'card_brand' => $cardInfo['brand'],
                'card_last4' => $cardInfo['last4']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment method added successfully',
                'payment_method' => [
                    'id' => $paymentMethod->id,
                    'card_holder_name' => $paymentMethod->card_holder_name,
                    'card_brand' => $paymentMethod->card_brand,
                    'card_last4' => $paymentMethod->card_last4,
                    'is_default' => $paymentMethod->is_default,
                    'created_at' => $paymentMethod->created_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create payment method', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment method. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get brand's payment methods
     */
    public function getPaymentMethods(): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can access payment methods',
            ], 403);
        }

        $paymentMethods = $user->brandPaymentMethods()
            ->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $paymentMethods->map(function ($method) {
                return [
                    'id' => $method->id,
                    'card_info' => $method->formatted_card_info,
                    'card_brand' => $method->card_brand,
                    'card_last4' => $method->card_last4,
                    'card_holder_name' => $method->card_holder_name,
                    'is_default' => $method->is_default,
                    'created_at' => $method->created_at,
                ];
            })
        ]);
    }

    /**
     * Set payment method as default
     */
    public function setDefaultPaymentMethod(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can manage payment methods',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|exists:brand_payment_methods,id,brand_id,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $paymentMethod = BrandPaymentMethod::find($request->payment_method_id);
        $paymentMethod->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => 'Default payment method updated successfully',
        ]);
    }

    /**
     * Delete payment method
     */
    public function deletePaymentMethod(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can manage payment methods',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|exists:brand_payment_methods,id,brand_id,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $paymentMethod = BrandPaymentMethod::find($request->payment_method_id);
        
        // Don't allow deletion of the only payment method
        if ($user->brandPaymentMethods()->active()->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the only payment method. Please add another one first.',
            ], 400);
        }

        $paymentMethod->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully',
        ]);
    }

    /**
     * Get customer from Pagar.me
     */
    private function getCustomerFromPagarMe(string $customerId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/customers/' . $customerId);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Error fetching customer from Pagar.me', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }
} 