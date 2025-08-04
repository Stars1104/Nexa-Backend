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
            // Check if this is a test card hash (for development)
            $isTestCard = strpos($request->card_hash, 'card_hash_') === 0;
            
            if ($isTestCard) {
                // For testing, create a mock card response
                $cardInfo = [
                    'id' => 'card_test_' . time(),
                    'brand' => 'visa',
                    'last_four_digits' => '4242',
                    'last4' => '4242', // Also provide last4 for compatibility
                    'holder_name' => $request->card_holder_name
                ];
                
                Log::info('Using test card info', [
                    'user_id' => $user->id,
                    'card_info' => $cardInfo
                ]);
                
                // For test cards, create a mock customer
                $customer = [
                    'id' => 'cus_test_' . time(),
                    'name' => $user->name,
                    'email' => $user->email
                ];
            } else {
                // Real Pagar.me integration - check if API key is configured
                if (empty($this->apiKey)) {
                    Log::error('Pagar.me API key not configured');
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment gateway not configured. Please contact support.',
                    ], 503);
                }

                // Validate API key format
                if (!preg_match('/^sk_(test|live)_[a-zA-Z0-9]+$/', $this->apiKey)) {
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
                                    'zip_code' => '01452000',
                                    'neighborhood' => 'Itaim Bibi',
                                    'city' => 'Sao Paulo',
                                    'state' => 'sp',
                                    'country' => 'br'
                                ]
                            ]
                        ]
                    ],
                    'customer_id' => $customer['id'],
                    'code' => 'CARD_REG_' . time(),
                    'items' => [
                        [
                            'amount' => 1,
                            'description' => 'Card Registration Test',
                            'quantity' => 1,
                            'code' => 'CARD_REG'
                        ]
                    ]
                ];

                $cardResponse = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl . '/orders', $testTransactionData);

                if (!$cardResponse->successful()) {
                    $errorResponse = $cardResponse->json();
                    Log::error('Failed to create card in Pagar.me', [
                        'user_id' => $user->id,
                        'response' => $errorResponse,
                        'status' => $cardResponse->status()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to register card. Please try again.',
                        'error' => $errorResponse
                    ], 400);
                }

                $card = $cardResponse->json();

                // Extract card information from the transaction response
                $cardInfo = $card['charges'][0]['payment_method_details']['card'] ?? null;
                
                if (!$cardInfo) {
                    Log::error('No card information found in PagarMe response', [
                        'user_id' => $user->id,
                        'response' => $card
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to extract card information. Please try again.',
                        'error' => 'No card information in response'
                    ], 400);
                }
            }

            // Step 3: Save payment method to database
            $paymentData = [
                'brand_id' => $user->id,
                'pagarme_customer_id' => $customer['id'],
                'pagarme_card_id' => $cardInfo['id'] ?? 'card_' . time(), // Use card ID from response or generate one
                'card_brand' => $cardInfo['brand'] ?? null,
                'card_last4' => $cardInfo['last_four_digits'] ?? $cardInfo['last4'] ?? null,
                'card_holder_name' => $request->card_holder_name,
                'is_default' => $request->get('is_default', false),
                'is_active' => true,
            ];
            
            Log::info('Creating payment method', [
                'user_id' => $user->id,
                'payment_data' => $paymentData
            ]);
            
            $paymentMethod = BrandPaymentMethod::create($paymentData);

            // Set as default if requested
            if ($request->get('is_default', false)) {
                $paymentMethod->setAsDefault();
            }

            Log::info('Payment method saved successfully', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethod->id,
                'customer_id' => $customer['id'],
                'card_id' => $cardInfo['id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment method saved successfully',
                'data' => [
                    'payment_method_id' => $paymentMethod->id,
                    'customer_id' => $customer['id'],
                    'card_id' => $cardInfo['id'],
                    'card_info' => $paymentMethod->formatted_card_info,
                    'is_default' => $paymentMethod->is_default,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving payment method', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving your payment method. Please try again.',
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