<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'whatsapp',
        'avatar_url',
        'avatar',
        'bio',
        'company_name',
        'whatsapp_number',
        'student_verified',
        'student_expires_at',
        'gender',
        'state',
        'language',
        'has_premium',
        'premium_expires_at',
        'free_trial_expires_at',
        'google_id',
        'google_token',
        'google_refresh_token',
        'email_verified_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'student_verified' => 'boolean',
        'student_expires_at' => 'datetime',
        'has_premium' => 'boolean',
        'premium_expires_at' => 'datetime',
        'free_trial_expires_at' => 'datetime',
    ];

    // Relationships
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'brand_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function approvedCampaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'approved_by');
    }

    public function campaignApplications(): HasMany
    {
        return $this->hasMany(CampaignApplication::class, 'creator_id');
    }

    public function reviewedApplications(): HasMany
    {
        return $this->hasMany(CampaignApplication::class, 'reviewed_by');
    }

    public function onlineStatus(): HasOne
    {
        return $this->hasOne(UserOnlineStatus::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function brandChatRooms(): HasMany
    {
        return $this->hasMany(ChatRoom::class, 'brand_id');
    }

    public function creatorChatRooms(): HasMany
    {
        return $this->hasMany(ChatRoom::class, 'creator_id');
    }

    public function brandDirectChatRooms(): HasMany
    {
        return $this->hasMany(DirectChatRoom::class, 'brand_id');
    }

    public function creatorDirectChatRooms(): HasMany
    {
        return $this->hasMany(DirectChatRoom::class, 'creator_id');
    }

    public function sentConnectionRequests(): HasMany
    {
        return $this->hasMany(ConnectionRequest::class, 'sender_id');
    }

    public function receivedConnectionRequests(): HasMany
    {
        return $this->hasMany(ConnectionRequest::class, 'receiver_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(\App\Models\Notification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->hasMany(\App\Models\Notification::class)->unread();
    }

    public function portfolio(): HasOne
    {
        return $this->hasOne(Portfolio::class);
    }

    /**
     * Check if the user has premium status.
     */
    public function isPremium(): bool
    {
        return $this->has_premium && 
               ($this->premium_expires_at === null || $this->premium_expires_at->isFuture());
    }

    /**
     * Check if the user is in free trial.
     */
    public function isOnTrial(): bool
    {
        return !$this->has_premium && 
               ($this->free_trial_expires_at !== null && $this->free_trial_expires_at->isFuture());
    }

    /**
     * Check if the user has bought premium (regardless of expiration).
     */
    public function hasBoughtPremium(): bool
    {
        return $this->has_premium;
    }

    /**
     * Check if the user has premium access (either premium or trial).
     */
    public function hasPremiumAccess(): bool
    {
        return $this->isPremium() || $this->isOnTrial();
    }

    /**
     * Check if the user is a verified student.
     */
    public function isVerifiedStudent(): bool
    {
        return $this->student_verified && 
               ($this->student_expires_at === null || $this->student_expires_at->isFuture());
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if the user is a creator.
     */
    public function isCreator(): bool
    {
        return $this->role === 'creator';
    }

    /**
     * Check if the user is a brand.
     */
    public function isBrand(): bool
    {
        return $this->role === 'brand';
    }

    /**
     * Get the user's display name (includes company name for brands).
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->isBrand() && $this->company_name) {
            return $this->name . ' (' . $this->company_name . ')';
        }
        return $this->name;
    }
}
