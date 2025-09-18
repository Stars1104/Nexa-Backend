<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\CreatorBalance;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentSimulator
{
    /**
     * Check if simulation mode is enabled
     */
    public static function isSimulationMode(): bool
    {
        return config('services.pagarme.simulation_mode', false);
    }

    /**
     * Simulate subscription payment
     */
    public static function simulateSubscriptionPayment(array $requestData, User $user, SubscriptionPlan $subscriptionPlan): array
    {
        Log::info('SIMULATION: Processing subscription payment', [
            'user_id' => $user->id,
            'plan_id' => $subscriptionPlan->id,
            'amount' => $subscriptionPlan->price,
        ]);

        // Simulate API delay
        usleep(500000); // 0.5 seconds

        // Generate fake transaction ID
        $transactionId = 'SIM_' . time() . '_' . $user->id . '_' . rand(1000, 9999);
        
        // Calculate expiration date
        $expiresAt = now()->addMonths($subscriptionPlan->duration_months);

        // Create transaction record
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'pagarme_transaction_id' => $transactionId,
            'status' => 'paid',
            'amount' => $subscriptionPlan->price,
            'payment_method' => 'credit_card',
            'card_brand' => self::getRandomCardBrand(),
            'card_last4' => substr($requestData['card_number'], -4),
            'card_holder_name' => $requestData['card_holder_name'],
            'payment_data' => [
                'simulation' => true,
                'original_request' => $requestData,
                'processed_at' => now()->toISOString(),
            ],
            'paid_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        // Create or update subscription
        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'subscription_plan_id' => $subscriptionPlan->id,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => $expiresAt,
                'amount_paid' => $subscriptionPlan->price,
                'payment_method' => 'credit_card',
                'transaction_id' => $transaction->id, // Use the actual transaction ID from database
                'auto_renew' => true,
            ]
        );

        // Update user premium status
        $user->update([
            'has_premium' => true,
            'premium_expires_at' => $expiresAt,
        ]);

        Log::info('SIMULATION: Subscription payment completed', [
            'transaction_id' => $transactionId,
            'subscription_id' => $subscription->id,
            'expires_at' => $expiresAt,
        ]);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => 'paid',
            'amount' => $subscriptionPlan->price,
            'expires_at' => $expiresAt->toISOString(),
            'simulation' => true,
        ];
    }

    /**
     * Simulate account payment
     */
    public static function simulateAccountPayment(array $requestData, User $user): array
    {
        Log::info('SIMULATION: Processing account payment', [
            'user_id' => $user->id,
            'amount' => $requestData['amount'],
            'account_id' => $requestData['account_id'],
        ]);

        // Simulate API delay
        usleep(300000); // 0.3 seconds

        // Generate fake transaction ID
        $transactionId = 'SIM_ACC_' . time() . '_' . $user->id . '_' . rand(1000, 9999);

        // Create transaction record
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'pagarme_transaction_id' => $transactionId,
            'status' => 'paid',
            'amount' => $requestData['amount'],
            'payment_method' => 'account_balance',
            'payment_data' => [
                'simulation' => true,
                'account_id' => $requestData['account_id'],
                'description' => $requestData['description'],
                'processed_at' => now()->toISOString(),
            ],
            'paid_at' => now(),
        ]);

        Log::info('SIMULATION: Account payment completed', [
            'transaction_id' => $transactionId,
            'amount' => $requestData['amount'],
        ]);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => 'paid',
            'amount' => $requestData['amount'],
            'simulation' => true,
        ];
    }

    /**
     * Simulate contract payment
     */
    public static function simulateContractPayment(array $requestData, User $user): array
    {
        Log::info('SIMULATION: Processing contract payment', [
            'user_id' => $user->id,
            'amount' => $requestData['amount'],
            'contract_id' => $requestData['contract_id'] ?? null,
        ]);

        // Simulate API delay
        usleep(400000); // 0.4 seconds

        // Generate fake transaction ID
        $transactionId = 'SIM_CONTRACT_' . time() . '_' . $user->id . '_' . rand(1000, 9999);

        // Create transaction record
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'pagarme_transaction_id' => $transactionId,
            'status' => 'paid',
            'amount' => $requestData['amount'],
            'payment_method' => 'credit_card',
            'card_brand' => self::getRandomCardBrand(),
            'card_last4' => '****',
            'card_holder_name' => $user->name,
            'payment_data' => [
                'simulation' => true,
                'contract_id' => $requestData['contract_id'] ?? null,
                'processed_at' => now()->toISOString(),
            ],
            'paid_at' => now(),
        ]);

        Log::info('SIMULATION: Contract payment completed', [
            'transaction_id' => $transactionId,
            'amount' => $requestData['amount'],
        ]);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => 'paid',
            'amount' => $requestData['amount'],
            'simulation' => true,
        ];
    }

    /**
     * Simulate withdrawal processing
     */
    public static function simulateWithdrawalProcessing(int $withdrawalId, string $method = 'bank_transfer'): array
    {
        Log::info('SIMULATION: Processing withdrawal', [
            'withdrawal_id' => $withdrawalId,
            'method' => $method,
        ]);

        // Simulate processing delay based on method
        $delay = match($method) {
            'pix' => 200000, // 0.2 seconds
            'bank_transfer' => 500000, // 0.5 seconds
            'pagarme_bank_transfer' => 300000, // 0.3 seconds
            default => 400000, // 0.4 seconds
        };
        
        usleep($delay);

        // Generate fake transaction ID
        $transactionId = 'SIM_WD_' . strtoupper($method) . '_' . time() . '_' . $withdrawalId;

        Log::info('SIMULATION: Withdrawal processing completed', [
            'withdrawal_id' => $withdrawalId,
            'transaction_id' => $transactionId,
            'method' => $method,
        ]);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => 'completed',
            'method' => $method,
            'simulation' => true,
        ];
    }

    /**
     * Simulate payment method creation
     */
    public static function simulatePaymentMethodCreation(array $requestData, User $user): array
    {
        Log::info('SIMULATION: Creating payment method', [
            'user_id' => $user->id,
            'card_last4' => substr($requestData['card_number'], -4),
        ]);

        // Simulate API delay
        usleep(200000); // 0.2 seconds

        // Generate fake card ID
        $cardId = 'SIM_CARD_' . time() . '_' . $user->id . '_' . rand(1000, 9999);

        return [
            'success' => true,
            'card_id' => $cardId,
            'brand' => self::getRandomCardBrand(),
            'last4' => substr($requestData['card_number'], -4),
            'exp_month' => $requestData['exp_month'],
            'exp_year' => $requestData['exp_year'],
            'holder_name' => $requestData['holder_name'],
            'simulation' => true,
        ];
    }

    /**
     * Get random card brand for simulation
     */
    private static function getRandomCardBrand(): string
    {
        $brands = ['visa', 'mastercard', 'amex', 'elo', 'hipercard'];
        return $brands[array_rand($brands)];
    }

    /**
     * Simulate API error (for testing)
     */
    public static function simulateError(string $message = 'Simulated payment error'): array
    {
        Log::info('SIMULATION: Simulating payment error', [
            'message' => $message,
        ]);

        return [
            'success' => false,
            'message' => $message,
            'error_code' => 'SIMULATION_ERROR',
            'simulation' => true,
        ];
    }

    /**
     * Check if we should simulate an error (for testing)
     */
    public static function shouldSimulateError(): bool
    {
        // 5% chance of error for testing
        return rand(1, 100) <= 5;
    }
}
