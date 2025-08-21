<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Carbon\Carbon;
use App\Services\NotificationService;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_id',
        'brand_id',
        'creator_id',
        'title',
        'description',
        'budget',
        'estimated_days',
        'requirements',
        'status',
        'started_at',
        'expected_completion_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'platform_fee',
        'creator_amount',
        'workflow_status', // New field for detailed workflow tracking
        'has_brand_review',
        'has_creator_review',
        'has_both_reviews',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'requirements' => 'array',
        'started_at' => 'datetime',
        'expected_completion_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'platform_fee' => 'decimal:2',
        'creator_amount' => 'decimal:2',
    ];

    // Relationships
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(User::class, 'brand_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(JobPayment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasManyThrough(Message::class, Offer::class, 'id', 'chat_room_id', 'offer_id', 'chat_room_id');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(CampaignTimeline::class);
    }

    public function deliveryMaterials(): HasMany
    {
        return $this->hasMany(DeliveryMaterial::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeDisputed($query)
    {
        return $query->where('status', 'disputed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'active')
                    ->where('expected_completion_at', '<', now());
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isDisputed(): bool
    {
        return $this->status === 'disputed';
    }

    public function isOverdue(): bool
    {
        return $this->isActive() && $this->expected_completion_at->isPast();
    }

    public function canBeCompleted(): bool
    {
        return $this->isActive();
    }

    public function canBeCancelled(): bool
    {
        return $this->isActive() && !$this->isCompleted();
    }

    /**
     * Check if contract can be terminated
     */
    public function canBeTerminated(): bool
    {
        return $this->isActive() && !$this->isCompleted();
    }

    /**
     * Terminate a contract (brand only)
     */
    public function terminate(string $reason = null): bool
    {
        if (!$this->canBeTerminated()) {
            return false;
        }

        $this->update([
            'status' => 'terminated',
            'workflow_status' => 'terminated',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason ?? 'Contract terminated by brand',
        ]);

        // Notify both parties about termination
        NotificationService::notifyUserOfContractTerminated($this, $reason);

        return true;
    }

    /**
     * Check if contract is waiting for review
     */
    public function isWaitingForReview(): bool
    {
        return $this->status === 'completed' && $this->workflow_status === 'waiting_review';
    }

    /**
     * Check if contract has been reviewed and payment is available
     */
    public function isPaymentAvailable(): bool
    {
        return $this->status === 'completed' && $this->workflow_status === 'payment_available';
    }

    /**
     * Check if contract payment has been withdrawn
     */
    public function isPaymentWithdrawn(): bool
    {
        return $this->status === 'completed' && $this->workflow_status === 'payment_withdrawn';
    }

    /**
     * Check if the brand has reviewed this contract
     */
    public function hasBrandReview(): bool
    {
        return $this->reviews()->where('reviewer_id', $this->brand_id)->exists();
    }

    /**
     * Check if the creator has reviewed this contract
     */
    public function hasCreatorReview(): bool
    {
        return $this->reviews()->where('reviewer_id', $this->creator_id)->exists();
    }

    /**
     * Check if both parties have reviewed each other
     */
    public function hasBothReviews(): bool
    {
        return $this->hasBrandReview() && $this->hasCreatorReview();
    }

    /**
     * Get the brand's review for this contract
     */
    public function getBrandReview()
    {
        return $this->reviews()->where('reviewer_id', $this->brand_id)->first();
    }

    /**
     * Get the creator's review for this contract
     */
    public function getCreatorReview()
    {
        return $this->reviews()->where('reviewer_id', $this->creator_id)->first();
    }

    /**
     * Update contract review status
     */
    public function updateReviewStatus(): void
    {
        $this->has_brand_review = $this->hasBrandReview();
        $this->has_creator_review = $this->hasCreatorReview();
        $this->has_both_reviews = $this->hasBothReviews();
        $this->save();
    }

    /**
     * Process payment after review is submitted
     */
    public function processPaymentAfterReview(): bool
    {
        // Only process payment for completed contracts where creator has reviewed the brand
        if ($this->status !== 'completed' || $this->workflow_status !== 'waiting_review') {
            return false;
        }

        // Check if creator has submitted a review
        $creatorReview = $this->reviews()->where('reviewer_id', $this->creator_id)->first();
        if (!$creatorReview) {
            return false;
        }

        // Update payment status to completed
        if ($this->payment) {
            $this->payment->update([
                'status' => 'completed',
            ]);
        }

        // Update creator balance
        $this->updateCreatorBalance($this->creator_amount);

        // Update workflow status
        $this->update([
            'workflow_status' => 'payment_available',
        ]);

        // Notify creator that funds are available
        NotificationService::notifyCreatorOfPaymentAvailable($this);

        return true;
    }

    /**
     * Check if contract payment has been processed
     */
    public function hasPaymentProcessed(): bool
    {
        return $this->payment && $this->payment->status === 'completed';
    }

    /**
     * Check if contract payment is pending
     */
    public function isPaymentPending(): bool
    {
        return $this->status === 'pending' && $this->workflow_status === 'payment_pending';
    }

    /**
     * Check if contract payment failed
     */
    public function isPaymentFailed(): bool
    {
        return $this->status === 'payment_failed' && $this->workflow_status === 'payment_failed';
    }

    /**
     * Check if contract can be started (payment processed)
     */
    public function canBeStarted(): bool
    {
        return $this->status === 'pending' && $this->hasPaymentProcessed();
    }

    /**
     * Retry payment for failed contracts
     */
    public function retryPayment(): bool
    {
        if (!$this->isPaymentFailed()) {
            return false;
        }

        $paymentService = new \App\Services\AutomaticPaymentService();
        $paymentResult = $paymentService->processContractPayment($this);

        if ($paymentResult['success']) {
            $this->update([
                'status' => 'active',
                'workflow_status' => 'active',
            ]);
            return true;
        }

        return false;
    }

    /**
     * Mark payment as withdrawn
     */
    public function markPaymentWithdrawn(): bool
    {
        if (!$this->isPaymentAvailable()) {
            return false;
        }

        $this->update([
            'workflow_status' => 'payment_withdrawn',
        ]);

        return true;
    }

    public function complete(): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }

        // Only allow completion of active contracts
        if ($this->status !== 'active') {
            return false;
        }

        // Calculate payment amounts
        $creatorAmount = $this->budget * 0.95;
        $platformFee = $this->budget * 0.05;

        // Create payment record immediately when brand completes the campaign
        JobPayment::create([
            'contract_id' => $this->id,
            'brand_id' => $this->brand_id,
            'creator_id' => $this->creator_id,
            'total_amount' => $this->budget,
            'platform_fee' => $platformFee,
            'creator_amount' => $creatorAmount,
            'payment_method' => 'platform_escrow',
            'status' => 'pending', // Payment is pending until creator reviews
        ]);

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'workflow_status' => 'waiting_review', // Waiting for creator to review brand
            'platform_fee' => $platformFee,
            'creator_amount' => $creatorAmount,
        ]);

        // Notify brand that review is required
        NotificationService::notifyBrandOfReviewRequired($this);

        // Notify creator that contract is completed and waiting for review
        NotificationService::notifyCreatorOfContractCompleted($this);

        return true;
    }

    /**
     * Send chat message about contract completion
     */
    // private function sendContractCompletionMessage(): void
    // {
    //     try {
    //         // Get the chat room for this contract
    //         $chatRoom = \App\Models\ChatRoom::whereHas('offers', function ($query) {
    //             $query->where('id', $this->offer_id);
    //         })->first();

    //         if ($chatRoom) {
    //             // Message for brand asking for review
    //             $brandReviewMessage = "🎉 Campanha finalizada com sucesso!\n\n" .
    //                 "A campanha foi finalizada e o pagamento está pronto para ser liberado. " .
    //                 "Por favor, envie sua avaliação para completar o processo e liberar o pagamento para o criador.\n\n" .
    //                 "Seu feedback ajuda a manter a qualidade de nossa plataforma e apoia outros usuários a tomar decisões informadas.";

    //             // Message for creator about payment release
    //             $creatorPaymentMessage = "🎉 Campanha finalizada com sucesso!\n\n" .
    //                 "A marca finalizou a campanha e o pagamento está pronto para ser liberado. " .
    //                 "Para receber seu pagamento, por favor, envie uma avaliação da marca.\n\n" .
    //                 "Após ambas as avaliações serem enviadas, você poderá sacar seu pagamento.";

    //             // For system messages, use the creator's ID as sender to avoid potential foreign key issues
    //             \App\Models\Message::create([
    //                 'chat_room_id' => $chatRoom->id,
    //                 'sender_id' => $this->creator_id,
    //                 'message' => $brandReviewMessage,
    //                 'message_type' => 'text',
    //                 'is_system_message' => true,
    //             ]);

    //             \App\Models\Message::create([
    //                 'chat_room_id' => $chatRoom->id,
    //                 'sender_id' => $this->creator_id,
    //                 'message' => $creatorPaymentMessage,
    //                 'message_type' => 'text',
    //                 'is_system_message' => true,
    //             ]);
    //         }
    //     } catch (\Exception $e) {
    //         \Illuminate\Support\Facades\Log::error('Failed to send contract completion message', [
    //             'contract_id' => $this->id,
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }

    public function cancel(string $reason = null): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        // Notify both parties about cancellation
        NotificationService::notifyUserOfContractCancelled($this, $reason);

        return true;
    }

    public function dispute(string $reason = null): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $this->update([
            'status' => 'disputed',
        ]);

        // Notify admin about dispute
        NotificationService::notifyAdminOfContractDispute($this, $reason);

        return true;
    }

    private function updateCreatorBalance(float $amount = null): void
    {
        $amount = $amount ?? $this->creator_amount;
        
        $balance = CreatorBalance::firstOrCreate(
            ['creator_id' => $this->creator_id],
            [
                'available_balance' => 0,
                'pending_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
            ]
        );

        // Add to available balance since payment is processed after review
        $balance->increment('available_balance', $amount);
        $balance->increment('total_earned', $amount);
    }

    public function getFormattedBudgetAttribute(): string
    {
        return 'R$ ' . number_format($this->budget, 2, ',', '.');
    }

    public function getFormattedCreatorAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->creator_amount, 2, ',', '.');
    }

    public function getFormattedPlatformFeeAttribute(): string
    {
        return 'R$ ' . number_format($this->platform_fee, 2, ',', '.');
    }

    public function getDaysUntilCompletionAttribute(): int
    {
        return max(0, now()->diffInDays($this->expected_completion_at, false));
    }

    public function getProgressPercentageAttribute(): int
    {
        $totalDays = $this->started_at->diffInDays($this->expected_completion_at);
        $elapsedDays = $this->started_at->diffInDays(now());
        
        return min(100, max(0, round(($elapsedDays / $totalDays) * 100)));
    }

    public function getIsNearCompletionAttribute(): bool
    {
        return $this->days_until_completion <= 2;
    }
} 