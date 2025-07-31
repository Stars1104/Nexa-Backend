<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Methods
    public function markAsRead(): bool
    {
        return $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function markAsUnread(): bool
    {
        return $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    // Static methods for creating notifications
    public static function createLoginDetected($userId, $loginData = []): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'login_detected',
            'title' => 'New Login Detected',
            'message' => 'A new login was detected on your account. If this wasn\'t you, please secure your account immediately.',
            'data' => $loginData,
        ]);
    }

    public static function createNewUserRegistration($userId, $registrationData = []): self
    {
        $userName = $registrationData['user_name'] ?? 'Unknown User';
        $userRole = $registrationData['user_role'] ?? 'Unknown Role';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'new_user_registration',
            'title' => 'New User Registration',
            'message' => "New {$userRole} registered: {$userName}",
            'data' => $registrationData,
        ]);
    }

    public static function createNewCampaign($userId, $campaignData = []): self
    {
        $brandName = $campaignData['brand_name'] ?? 'Unknown Brand';
        $campaignTitle = $campaignData['campaign_title'] ?? 'Unknown Campaign';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'new_campaign',
            'title' => 'New Campaign Created',
            'message' => "{$brandName} posted new campaign: {$campaignTitle}",
            'data' => $campaignData,
        ]);
    }

    public static function createNewApplication($userId, $applicationData = []): self
    {
        $creatorName = $applicationData['creator_name'] ?? 'Unknown Creator';
        $campaignTitle = $applicationData['campaign_title'] ?? 'Unknown Campaign';
        $brandName = $applicationData['brand_name'] ?? 'Unknown Brand';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'new_application',
            'title' => 'New Campaign Application',
            'message' => "{$creatorName} applied to {$brandName}'s campaign: {$campaignTitle}",
            'data' => $applicationData,
        ]);
    }

    public static function createNewBid($userId, $bidData = []): self
    {
        $campaignTitle = $bidData['campaign_title'] ?? 'Unknown Campaign';
        $creatorName = $bidData['creator_name'] ?? 'Unknown Creator';
        $bidAmount = $bidData['bid_amount'] ?? 'Unknown Amount';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'new_bid',
            'title' => 'New Bid Received',
            'message' => "New bid from {$creatorName} for '{$campaignTitle}' - R$ {$bidAmount}",
            'data' => $bidData,
        ]);
    }

    public static function createPaymentActivity($userId, $paymentData = []): self
    {
        $paymentType = $paymentData['payment_type'] ?? 'Unknown Payment';
        $userName = $paymentData['user_name'] ?? 'Unknown User';
        $amount = $paymentData['amount'] ?? 'Unknown Amount';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'payment_activity',
            'title' => 'Payment Activity',
            'message' => "{$paymentType} from {$userName} - {$amount}",
            'data' => $paymentData,
        ]);
    }

    public static function createPortfolioUpdate($userId, $portfolioData = []): self
    {
        $userName = $portfolioData['user_name'] ?? 'Unknown User';
        $updateType = $portfolioData['update_type'] ?? 'Unknown Update';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'portfolio_update',
            'title' => 'Portfolio Update',
            'message' => "{$userName} updated their portfolio: {$updateType}",
            'data' => $portfolioData,
        ]);
    }

    public static function createSystemActivity($userId, $activityData = []): self
    {
        $activityType = $activityData['activity_type'] ?? 'Unknown Activity';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'system_activity',
            'title' => 'System Activity',
            'message' => "System activity detected: {$activityType}",
            'data' => $activityData,
        ]);
    }

    public static function createNewProject($userId, $campaignId, $campaignTitle): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'new_project',
            'title' => 'New Project Available',
            'message' => "A new project '{$campaignTitle}' is now available for applications.",
            'data' => [
                'campaign_id' => $campaignId,
                'campaign_title' => $campaignTitle,
            ],
        ]);
    }

    public static function createProjectApproved($userId, $campaignId, $campaignTitle): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'project_approved',
            'title' => 'Project Approved',
            'message' => "Your project '{$campaignTitle}' has been approved and is now live.",
            'data' => [
                'campaign_id' => $campaignId,
                'campaign_title' => $campaignTitle,
            ],
        ]);
    }

    public static function createProjectRejected($userId, $campaignId, $campaignTitle, $reason = null): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'project_rejected',
            'title' => 'Project Rejected',
            'message' => "Your project '{$campaignTitle}' was not approved." . ($reason ? " Reason: {$reason}" : ''),
            'data' => [
                'campaign_id' => $campaignId,
                'campaign_title' => $campaignTitle,
                'rejection_reason' => $reason,
            ],
        ]);
    }

    public static function createProposalApproved($userId, $campaignId, $campaignTitle, $brandName): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'proposal_approved',
            'title' => 'Proposal Approved',
            'message' => "Your proposal for '{$campaignTitle}' has been approved by {$brandName}.",
            'data' => [
                'campaign_id' => $campaignId,
                'campaign_title' => $campaignTitle,
                'brand_name' => $brandName,
            ],
        ]);
    }

    public static function createProposalRejected($userId, $campaignId, $campaignTitle, $brandName, $reason = null): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'proposal_rejected',
            'title' => 'Proposal Rejected',
            'message' => "Your proposal for '{$campaignTitle}' was not selected by {$brandName}." . ($reason ? " Reason: {$reason}" : ''),
            'data' => [
                'campaign_id' => $campaignId,
                'campaign_title' => $campaignTitle,
                'brand_name' => $brandName,
                'rejection_reason' => $reason,
            ],
        ]);
    }

    public static function createNewMessage($userId, $senderId, $senderName, $messagePreview, $chatType = 'direct'): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'new_message',
            'title' => 'New Message',
            'message' => "You have a new message from {$senderName}: {$messagePreview}",
            'data' => [
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                'message_preview' => $messagePreview,
                'chat_type' => $chatType,
            ],
        ]);
    }

    public static function createContractTerminated($userId, $contractData = []): self
    {
        $reason = $contractData['reason'] ?? 'Contract terminated';
        
        return self::create([
            'user_id' => $userId,
            'type' => 'contract_terminated',
            'title' => 'Contract Terminated',
            'message' => "Contract has been terminated: {$reason}",
            'data' => $contractData,
        ]);
    }

    public static function createPaymentAvailable($userId, $paymentData = []): self
    {
        $creatorAmount = $paymentData['creator_amount'] ?? 0;
        
        return self::create([
            'user_id' => $userId,
            'type' => 'payment_available',
            'title' => 'Payment Available',
            'message' => "Payment of R$ {$creatorAmount} is now available for withdrawal",
            'data' => $paymentData,
        ]);
    }
} 