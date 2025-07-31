<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CampaignApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'creator_id',
        'status',
        'proposal',
        'portfolio_links',
        'estimated_delivery_days',
        'proposed_budget',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'approved_at'
    ];

    protected $casts = [
        'portfolio_links' => 'array',
        'proposed_budget' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByCreator($query, $creatorId)
    {
        return $query->where('creator_id', $creatorId);
    }

    public function scopeByCampaign($query, $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    // Methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function approve($brandId): bool
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $brandId,
            'reviewed_at' => now(),
            'approved_at' => now(),
            'rejection_reason' => null
        ]);

        return true;
    }

    public function reject($brandId, $reason = null): bool
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $brandId,
            'reviewed_at' => now(),
            'rejection_reason' => $reason
        ]);

        return true;
    }

    public function canBeReviewedBy($user): bool
    {
        return $this->campaign->brand_id === $user->id && $this->isPending();
    }

    public function canBeWithdrawnBy($user): bool
    {
        return $this->creator_id === $user->id && $this->isPending();
    }
}
