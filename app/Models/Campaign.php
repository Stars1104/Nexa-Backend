<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\NotificationService;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'title',
        'description',
        'budget',
        'final_price',
        'location',
        'requirements',
        'target_states',
        'category',
        'campaign_type',
        'image_url',
        'logo',
        'attach_file',
        'status',
        'deadline',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'max_bids',
        'is_active',
        'is_featured'
    ];

    protected $casts = [
        'target_states' => 'array',
        'deadline' => 'date',
        'approved_at' => 'datetime',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'budget' => 'decimal:2',
        'final_price' => 'decimal:2',
    ];

    // Relationships
    public function brand(): BelongsTo
    {
        return $this->belongsTo(User::class, 'brand_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CampaignApplication::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(CampaignFavorite::class);
    }

    public function isFavoritedBy($creatorId): bool
    {
        return $this->favorites()->where('creator_id', $creatorId)->exists();
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForState($query, $state)
    {
        return $query->whereJsonContains('target_states', $state);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('campaign_type', $type);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
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

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canReceiveBids(): bool
    {
        return $this->isApproved() && 
               $this->is_active && 
               $this->deadline >= now()->toDateString() &&
               $this->bids()->count() < $this->max_bids;
    }

    public function approve($adminId): bool
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $adminId,
            'rejection_reason' => null
        ]);

        // Notify brand about project approval
        NotificationService::notifyBrandOfProjectStatus($this, 'approved');

        // Notify creators about new project
        NotificationService::notifyCreatorsOfNewProject($this);

        return true;
    }

    public function reject($adminId, $reason = null): bool
    {
        $this->update([
            'status' => 'rejected',
            'approved_at' => null,
            'approved_by' => $adminId,
            'rejection_reason' => $reason
        ]);

        // Notify brand about project rejection
        NotificationService::notifyBrandOfProjectStatus($this, 'rejected', $reason);

        return true;
    }

    public function complete(): bool
    {
        $this->update([
            'status' => 'completed',
            'is_active' => false
        ]);

        return true;
    }

    public function cancel(): bool
    {
        $this->update([
            'status' => 'cancelled',
            'is_active' => false
        ]);

        return true;
    }

    public function getTotalBidsAttribute(): int
    {
        return $this->bids()->count();
    }

    public function getAcceptedBidAttribute()
    {
        return $this->bids()->where('status', 'accepted')->first();
    }

    public function hasAcceptedBid(): bool
    {
        return $this->bids()->where('status', 'accepted')->exists();
    }
}
