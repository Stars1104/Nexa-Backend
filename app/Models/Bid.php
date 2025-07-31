<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\NotificationService;

class Bid extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'bid_amount',
        'proposal',
        'portfolio_links',
        'estimated_delivery_days',
        'status',
        'accepted_at',
        'rejection_reason'
    ];

    protected $casts = [
        'bid_amount' => 'decimal:2',
        'accepted_at' => 'datetime',
    ];

    // Relationships
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isWithdrawn(): bool
    {
        return $this->status === 'withdrawn';
    }

    public function accept(): bool
    {
        // Only one bid can be accepted per campaign
        $this->campaign->bids()->where('id', '!=', $this->id)->update(['status' => 'rejected']);
        
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now()
        ]);

        // Update campaign with final price
        $this->campaign->update([
            'final_price' => $this->bid_amount
        ]);

        // Notify creator about proposal acceptance
        NotificationService::notifyCreatorOfProposalStatus($this, 'accepted');

        return true;
    }

    public function reject($reason = null): bool
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason
        ]);

        // Notify creator about proposal rejection
        NotificationService::notifyCreatorOfProposalStatus($this, 'rejected', $reason);

        return true;
    }

    public function withdraw(): bool
    {
        $this->update([
            'status' => 'withdrawn'
        ]);

        return true;
    }
}
