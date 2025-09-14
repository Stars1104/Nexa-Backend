<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'reviewer_id',
        'reviewed_id',
        'rating',
        'comment',
        'rating_categories',
        'is_public',
    ];

    protected $casts = [
        'rating_categories' => 'array',
        'is_public' => 'boolean',
    ];

    // Relationships
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_id');
    }

    // Scopes
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeForCreator($query, $creatorId)
    {
        return $query->where('reviewed_id', $creatorId);
    }

    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    public function scopeHighRating($query, $minRating = 4)
    {
        return $query->where('rating', '>=', $minRating);
    }

    // Methods
    public function getAverageRatingAttribute(): float
    {
        if ($this->rating_categories && is_array($this->rating_categories)) {
            $sum = array_sum($this->rating_categories);
            $count = count($this->rating_categories);
            return $count > 0 ? round($sum / $count, 1) : $this->rating;
        }
        
        return $this->rating;
    }

    public function getRatingStarsAttribute(): string
    {
        $rating = $this->average_rating;
        $fullStars = floor($rating);
        $halfStar = $rating - $fullStars >= 0.5;
        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
        
        return str_repeat('★', $fullStars) . 
               ($halfStar ? '☆' : '') . 
               str_repeat('☆', $emptyStars);
    }

    public function getFormattedRatingAttribute(): string
    {
        return number_format($this->average_rating, 1) . '/5.0';
    }

    public function getRatingCategoryAttribute(): string
    {
        $rating = $this->average_rating;
        
        if ($rating >= 4.5) return 'excellent';
        if ($rating >= 4.0) return 'very_good';
        if ($rating >= 3.5) return 'good';
        if ($rating >= 3.0) return 'average';
        if ($rating >= 2.0) return 'below_average';
        return 'poor';
    }

    public function getRatingColorAttribute(): string
    {
        $rating = $this->average_rating;
        
        if ($rating >= 4.5) return 'text-green-600';
        if ($rating >= 4.0) return 'text-green-500';
        if ($rating >= 3.5) return 'text-yellow-500';
        if ($rating >= 3.0) return 'text-yellow-600';
        if ($rating >= 2.0) return 'text-orange-500';
        return 'text-red-500';
    }

    public function isHighRating(): bool
    {
        return $this->average_rating >= 4.0;
    }

    public function isLowRating(): bool
    {
        return $this->average_rating < 3.0;
    }

    public function getCategoryRating(string $category): ?float
    {
        if (!$this->rating_categories || !is_array($this->rating_categories)) {
            return null;
        }
        
        return $this->rating_categories[$category] ?? null;
    }

    public function getCategoryRatingStars(string $category): string
    {
        $rating = $this->getCategoryRating($category);
        if ($rating === null) return '';
        
        $fullStars = floor($rating);
        $halfStar = $rating - $fullStars >= 0.5;
        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
        
        return str_repeat('★', $fullStars) . 
               ($halfStar ? '☆' : '') . 
               str_repeat('☆', $emptyStars);
    }

    protected static function booted()
    {
        static::created(function ($review) {
            try {
                // Notify creator about new review
                NotificationService::notifyUserOfNewReview($review);
            } catch (\Exception $e) {
                Log::error('Failed to notify user of new review', [
                    'review_id' => $review->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            try {
                // Update creator's average rating
                $review->updateCreatorAverageRating();
            } catch (\Exception $e) {
                Log::error('Failed to update creator average rating', [
                    'review_id' => $review->id,
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    private function updateCreatorAverageRating(): void
    {
        $creator = $this->reviewed;
        if (!$creator) {
            Log::warning('Cannot update creator rating: reviewed user not found', [
                'review_id' => $this->id,
                'reviewed_id' => $this->reviewed_id
            ]);
            return;
        }

        $publicReviews = Review::where('reviewed_id', $creator->id)
            ->where('is_public', true);

        $averageRating = $publicReviews->avg('rating');
        $totalReviews = $publicReviews->count();

        $creator->update([
            'average_rating' => $averageRating ? round($averageRating, 1) : null,
            'total_reviews' => $totalReviews,
        ]);

        Log::info('Updated creator review stats', [
            'creator_id' => $creator->id,
            'total_reviews' => $totalReviews,
            'average_rating' => $averageRating ? round($averageRating, 1) : null,
        ]);
    }
} 