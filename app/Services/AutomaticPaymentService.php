<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\JobPayment;
use Illuminate\Support\Facades\Log;

class AutomaticPaymentService
{
    /**
     * Process payment for a contract
     */
    public function processContractPayment(Contract $contract): array
    {
        try {
            // For now, we'll simulate a successful payment
            // In a real implementation, this would integrate with a payment gateway
            
            // Create a payment record
            $payment = JobPayment::create([
                'contract_id' => $contract->id,
                'brand_id' => $contract->brand_id,
                'creator_id' => $contract->creator_id,
                'total_amount' => $contract->budget,
                'creator_amount' => $contract->creator_amount,
                'platform_fee' => $contract->platform_fee,
                'payment_method' => 'credit_card', // Default payment method
                'status' => 'completed', // Simulate successful payment
                'processed_at' => now(),
            ]);

            // Update contract status to active
            $contract->update([
                'status' => 'active',
                'workflow_status' => 'active',
            ]);

            Log::info('Payment processed successfully', [
                'contract_id' => $contract->id,
                'payment_id' => $payment->id,
                'amount' => $contract->budget,
            ]);

            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'payment_id' => $payment->id,
            ];

        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retry payment for failed contracts
     */
    public function retryPayment(Contract $contract): array
    {
        return $this->processContractPayment($contract);
    }
} 