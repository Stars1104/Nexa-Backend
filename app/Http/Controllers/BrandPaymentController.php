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
            'customer_id' => 'required|string',
            'payment_method_id' => 'required|string',
            'is_default' => 'required|boolean',
            'card_brand' => 'required|string',
            'card_last4' => 'required|string|size:4',
            'card_exp_month' => 'required|integer|min:1|max:12',
            'card_exp_year' => 'required|integer|min:' . date('Y'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Extract card information from the card_hash (simplified approach)
            // In a real implementation, you would decrypt or parse the card_hash
            $cardInfo = $this->parseCardInfo($request->card_hash, $request->card_holder_name);

            // Check if this card already exists for the user
            $existingMethod = BrandPaymentMethod::where('user_id', $user->id)
                ->where('card_hash', $request->card_hash)
                ->where('is_active', true)
                ->first();

            if ($existingMethod) {
                return response()->json([
                    'success' => false,
                    'message' => 'This payment method already exists',
                ], 400);
            }

            // Save payment method to database
            $paymentMethod = BrandPaymentMethod::create([
                'user_id' => $user->id,
                'card_holder_name' => $request->card_holder_name,
                'card_brand' => $cardInfo['brand'],
                'card_last4' => $cardInfo['last4'],
                'is_default' => $request->is_default ?? false,
                'card_hash' => $request->card_hash,
                'is_active' => true,
            ]);

            // If this is set as default, unset other default methods
            if ($request->is_default) {
                BrandPaymentMethod::where('brand_id', $user->id)
                    ->where('id', '!=', $paymentMethod->id)
                    ->update(['is_default' => false]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment method added successfully',
                'data' => [
                    'payment_method_id' => $paymentMethod->id,
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
                    'customer_id' => $method->customer_id,
                    'payment_method_id' => $method->payment_method_id,
                    'is_default' => $method->is_default,
                    'card_holder_name' => $method->card_holder_name,
                    'card_brand' => $method->card_brand,
                    'card_last4' => $method->card_last4,
                    'card_exp_month' => $method->card_exp_month,
                    'card_exp_year' => $method->card_exp_year,
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
            'payment_method_id' => 'required|exists:brand_payment_methods,id,user_id,' . $user->id,
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
            'payment_method_id' => 'required|exists:brand_payment_methods,id,user_id,' . $user->id,
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
     * Parse card information from card hash
     * This is a simplified implementation for testing
     */
    private function parseCardInfo(string $cardHash, string $cardHolderName): array
    {
        // Extract last 4 digits from card hash (simplified approach)
        // In a real implementation, you would decrypt the card hash
        $last4 = substr($cardHash, -4);
        
        // Determine card brand based on hash pattern (simplified)
        $brand = 'Visa'; // Default brand
        
        if (strpos($cardHash, 'master') !== false) {
            $brand = 'Mastercard';
        } elseif (strpos($cardHash, 'amex') !== false) {
            $brand = 'American Express';
        } elseif (strpos($cardHash, 'elo') !== false) {
            $brand = 'Elo';
        }

        return [
            'brand' => $brand,
            'last4' => $last4,
            'holder_name' => $cardHolderName
        ];
    }
} 