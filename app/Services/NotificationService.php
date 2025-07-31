<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Campaign;
use App\Models\Bid;
use App\Models\Message;
use App\Models\DirectMessage;
use App\Models\CampaignApplication;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send notification to admin about new login
     */
    public static function notifyAdminOfNewLogin(User $user, array $loginData = []): void
    {
        try {
            // Get all admin users
            $adminUsers = User::where('role', 'admin')->get();
            
            foreach ($adminUsers as $admin) {
                $notification = Notification::createLoginDetected($admin->id, array_merge($loginData, [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                ]));
                
                // Send real-time notification via Socket.IO
                self::sendSocketNotification($admin->id, $notification);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admin of new login', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to admin about new user registration
     */
    public static function notifyAdminOfNewRegistration(User $user): void
    {
        try {
            // Get all admin users
            $adminUsers = User::where('role', 'admin')->get();
            
            foreach ($adminUsers as $admin) {
                $notification = Notification::createNewUserRegistration($admin->id, [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'registration_time' => now()->toISOString(),
                ]);
                
                // Send real-time notification via Socket.IO
                self::sendSocketNotification($admin->id, $notification);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admin of new registration', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to admin about new campaign creation
     */
    public static function notifyAdminOfNewCampaign(Campaign $campaign): void
    {
        try {
            // Get all admin users
            $adminUsers = User::where('role', 'admin')->get();
            
            foreach ($adminUsers as $admin) {
                $notification = Notification::createNewCampaign($admin->id, [
                    'campaign_id' => $campaign->id,
                    'campaign_title' => $campaign->title,
                    'brand_id' => $campaign->brand_id,
                    'brand_name' => $campaign->brand->name,
                    'brand_email' => $campaign->brand->email,
                    'budget' => $campaign->budget,
                    'category' => $campaign->category,
                    'campaign_type' => $campaign->campaign_type,
                    'created_at' => $campaign->created_at->toISOString(),
                ]);
                
                // Send real-time notification via Socket.IO
                self::sendSocketNotification($admin->id, $notification);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admin of new campaign', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to admin about new campaign application
     */
    public static function notifyAdminOfNewApplication(CampaignApplication $application): void
    {
        try {
            // Get all admin users
            $adminUsers = User::where('role', 'admin')->get();
            
            foreach ($adminUsers as $admin) {
                $notification = Notification::createNewApplication($admin->id, [
                    'application_id' => $application->id,
                    'campaign_id' => $application->campaign_id,
                    'campaign_title' => $application->campaign->title,
                    'creator_id' => $application->creator_id,
                    'creator_name' => $application->creator->name,
                    'creator_email' => $application->creator->email,
                    'brand_id' => $application->campaign->brand_id,
                    'brand_name' => $application->campaign->brand->name,
                    'proposal_amount' => $application->proposal_amount,
                    'created_at' => $application->created_at->toISOString(),
                ]);
                
                // Send real-time notification via Socket.IO
                self::sendSocketNotification($admin->id, $notification);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admin of new application', [
                'application_id' => $application->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to admin about new bid submission
     */
    public static function notifyAdminOfNewBid(Bid $bid): void
    {
        try {
            // Get all admin users
            $adminUsers = User::where('role', 'admin')->get();
            
            foreach ($adminUsers as $admin) {
                $notification = Notification::createNewBid($admin->id, [
                    'bid_id' => $bid->id,
                    'campaign_id' => $bid->campaign_id,
                    'campaign_title' => $bid->campaign->title,
                    'creator_id' => $bid->user_id,
                    'creator_name' => $bid->user->name,
                    'creator_email' => $bid->user->email,
                    'brand_id' => $bid->campaign->brand_id,
                    'brand_name' => $bid->campaign->brand->name,
                    'bid_amount' => $bid->amount,
                    'created_at' => $bid->created_at->toISOString(),
                ]);
                
                // Send real-time notification via Socket.IO
                self::sendSocketNotification($admin->id, $notification);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admin of new bid', [
                'bid_id' => $bid->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to admin about payment activity
     */
    public static function notifyAdminOfPaymentActivity(User $user, string $paymentType, array $paymentData = []): void
    {
        try {
            // Get all admin users
            $adminUsers = User::where('role', 'admin')->get();
            
            foreach ($adminUsers as $admin) {
                $notification = Notification::createPaymentActivity($admin->id, array_merge($paymentData, [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'payment_type' => $paymentType,
                    'activity_time' => now()->toISOString(),
                ]));
                
                // Send real-time notification via Socket.IO
                self::sendSocketNotification($admin->id, $notification);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admin of payment activity', [
                'user_id' => $user->id,
                'payment_type' => $paymentType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to admin about portfolio update
     */
    public static function notifyAdminOfPortfolioUpdate(User $user, string $updateType, array $updateData = []): void
    {
        try {
            // Get all admin users
            $adminUsers = User::where('role', 'admin')->get();
            
            foreach ($adminUsers as $admin) {
                $notification = Notification::createPortfolioUpdate($admin->id, array_merge($updateData, [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'update_type' => $updateType,
                    'update_time' => now()->toISOString(),
                ]));
                
                // Send real-time notification via Socket.IO
                self::sendSocketNotification($admin->id, $notification);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admin of portfolio update', [
                'user_id' => $user->id,
                'update_type' => $updateType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to admin about system activity
     */
    public static function notifyAdminOfSystemActivity(string $activityType, array $activityData = []): void
    {
        try {
            // Get all admin users
            $adminUsers = User::where('role', 'admin')->get();
            
            foreach ($adminUsers as $admin) {
                $notification = Notification::createSystemActivity($admin->id, array_merge($activityData, [
                    'activity_type' => $activityType,
                    'activity_time' => now()->toISOString(),
                ]));
                
                // Send real-time notification via Socket.IO
                self::sendSocketNotification($admin->id, $notification);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admin of system activity', [
                'activity_type' => $activityType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify creators about new project
     */
    public static function notifyCreatorsOfNewProject(Campaign $campaign): void
    {
        try {
            // Get all creator users
            $creators = User::where('role', 'creator')->get();
            
            foreach ($creators as $creator) {
                $notification = Notification::createNewProject(
                    $creator->id,
                    $campaign->id,
                    $campaign->title
                );
                
                // Send real-time notification via Socket.IO
                self::sendSocketNotification($creator->id, $notification);
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify creators of new project', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify brand about project approval/rejection
     */
    public static function notifyBrandOfProjectStatus(Campaign $campaign, string $status, string $reason = null): void
    {
        try {
            if ($status === 'approved') {
                $notification = Notification::createProjectApproved(
                    $campaign->brand_id,
                    $campaign->id,
                    $campaign->title
                );
            } else {
                $notification = Notification::createProjectRejected(
                    $campaign->brand_id,
                    $campaign->id,
                    $campaign->title,
                    $reason
                );
            }
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($campaign->brand_id, $notification);
        } catch (\Exception $e) {
            Log::error('Failed to notify brand of project status', [
                'campaign_id' => $campaign->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify creator about proposal approval/rejection
     */
    public static function notifyCreatorOfProposalStatus(Bid $bid, string $status, string $reason = null): void
    {
        try {
            $campaign = $bid->campaign;
            $brand = $campaign->brand;
            
            if ($status === 'accepted') {
                $notification = Notification::createProposalApproved(
                    $bid->user_id,
                    $campaign->id,
                    $campaign->title,
                    $brand->name
                );
            } else {
                $notification = Notification::createProposalRejected(
                    $bid->user_id,
                    $campaign->id,
                    $campaign->title,
                    $brand->name,
                    $reason
                );
            }
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($bid->user_id, $notification);
        } catch (\Exception $e) {
            Log::error('Failed to notify creator of proposal status', [
                'bid_id' => $bid->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify user about new message
     */
    public static function notifyUserOfNewMessage(Message $message): void
    {
        try {
            $chatRoom = $message->chatRoom;
            $sender = $message->sender;
            
            // Determine recipient
            $recipientId = $chatRoom->brand_id === $sender->id ? $chatRoom->creator_id : $chatRoom->brand_id;
            
            // Create message preview (truncate if too long)
            $messagePreview = strlen($message->message) > 50 
                ? substr($message->message, 0, 50) . '...' 
                : $message->message;
            
            $notification = Notification::createNewMessage(
                $recipientId,
                $sender->id,
                $sender->name,
                $messagePreview,
                'campaign'
            );
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($recipientId, $notification);
        } catch (\Exception $e) {
            Log::error('Failed to notify user of new message', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify user about new direct message
     */
    public static function notifyUserOfNewDirectMessage(DirectMessage $message): void
    {
        try {
            $chatRoom = $message->directChatRoom;
            $sender = $message->sender;
            
            // Determine recipient
            $recipientId = $chatRoom->brand_id === $sender->id ? $chatRoom->creator_id : $chatRoom->brand_id;
            
            // Create message preview (truncate if too long)
            $messagePreview = strlen($message->message) > 50 
                ? substr($message->message, 0, 50) . '...' 
                : $message->message;
            
            $notification = Notification::createNewMessage(
                $recipientId,
                $sender->id,
                $sender->name,
                $messagePreview,
                'direct'
            );
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($recipientId, $notification);
        } catch (\Exception $e) {
            Log::error('Failed to notify user of new direct message', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification via Socket.IO
     */
    public static function sendSocketNotification(int $userId, Notification $notification): void
    {
        try {
            // Get the socket server instance from global scope
            $socketServer = null;
            
            // Try to get from Laravel service container first
            if (app()->bound('socket.server')) {
                $socketServer = app('socket.server');
            }
            
            // If not available in container, try global scope
            if (!$socketServer && isset($GLOBALS['socket_server'])) {
                $socketServer = $GLOBALS['socket_server'];
            }
            
            if ($socketServer) {
                $notificationData = [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $notification->data,
                    'is_read' => $notification->is_read,
                    'created_at' => $notification->created_at->toISOString(),
                ];
                
                $socketServer->to("user_{$userId}")->emit('new_notification', $notificationData);
                
                Log::info('Socket notification sent successfully', [
                    'user_id' => $userId,
                    'notification_id' => $notification->id,
                    'room' => "user_{$userId}"
                ]);
            } else {
                Log::warning('Socket server not available for notification', [
                    'user_id' => $userId,
                    'notification_id' => $notification->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send socket notification', [
                'user_id' => $userId,
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get unread notification count for user
     */
    public static function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark notification as read
     */
    public static function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();
            
        if ($notification) {
            return $notification->markAsRead();
        }
        
        return false;
    }

    /**
     * Mark all notifications as read for user
     */
    public static function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Notify creator about new offer
     */
    public static function notifyUserOfNewOffer($offer): void
    {
        try {
            $notification = Notification::create([
                'user_id' => $offer->creator_id,
                'type' => 'new_offer',
                'title' => 'Nova Oferta Recebida',
                'message' => "Você recebeu uma nova oferta de R$ {$offer->formatted_budget}",
                'data' => [
                    'offer_id' => $offer->id,
                    'brand_id' => $offer->brand_id,
                    'brand_name' => $offer->brand->name,
                    'budget' => $offer->budget,
                    'estimated_days' => $offer->estimated_days,
                ],
                'is_read' => false,
            ]);
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($offer->creator_id, $notification);
        } catch (\Exception $e) {
            Log::error('Failed to notify user of new offer', [
                'offer_id' => $offer->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify brand about offer acceptance
     */
    public static function notifyUserOfOfferAccepted($offer): void
    {
        try {
            $notification = Notification::create([
                'user_id' => $offer->brand_id,
                'type' => 'offer_accepted',
                'title' => 'Oferta Aceita',
                'message' => "Sua oferta foi aceita pelo criador",
                'data' => [
                    'offer_id' => $offer->id,
                    'creator_id' => $offer->creator_id,
                    'creator_name' => $offer->creator->name,
                    'budget' => $offer->budget,
                    'contract_id' => $offer->contract->id ?? null,
                ],
                'is_read' => false,
            ]);
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($offer->brand_id, $notification);
        } catch (\Exception $e) {
            Log::error('Failed to notify user of offer acceptance', [
                'offer_id' => $offer->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify brand about offer rejection
     */
    public static function notifyUserOfOfferRejected($offer, string $reason = null): void
    {
        try {
            $notification = Notification::create([
                'user_id' => $offer->brand_id,
                'type' => 'offer_rejected',
                'title' => 'Oferta Rejeitada',
                'message' => "Sua oferta foi rejeitada pelo criador" . ($reason ? ": {$reason}" : ""),
                'data' => [
                    'offer_id' => $offer->id,
                    'creator_id' => $offer->creator_id,
                    'creator_name' => $offer->creator->name,
                    'rejection_reason' => $reason,
                ],
                'is_read' => false,
            ]);
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($offer->brand_id, $notification);
        } catch (\Exception $e) {
            Log::error('Failed to notify user of offer rejection', [
                'offer_id' => $offer->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify creator about offer cancellation
     */
    public static function notifyUserOfOfferCancelled($offer): void
    {
        try {
            // Notify creator about cancelled offer
            $notification = Notification::createOfferCancelled($offer->creator_id, [
                'offer_id' => $offer->id,
                'offer_title' => $offer->title,
                'brand_id' => $offer->brand_id,
                'brand_name' => $offer->brand->name,
                'cancelled_at' => now()->toISOString(),
            ]);
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($offer->creator_id, $notification);
            
        } catch (\Exception $e) {
            Log::error('Failed to notify user of offer cancellation', [
                'offer_id' => $offer->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to brand about review requirement
     */
    public static function notifyBrandOfReviewRequired($contract): void
    {
        try {
            $notification = Notification::createReviewRequired($contract->brand_id, [
                'contract_id' => $contract->id,
                'contract_title' => $contract->title,
                'creator_id' => $contract->creator_id,
                'creator_name' => $contract->creator->name,
                'completed_at' => $contract->completed_at->toISOString(),
            ]);
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($contract->brand_id, $notification);
            
        } catch (\Exception $e) {
            Log::error('Failed to notify brand of review requirement', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to creator about contract completion
     */
    public static function notifyCreatorOfContractCompleted($contract): void
    {
        try {
            $notification = Notification::createContractCompleted($contract->creator_id, [
                'contract_id' => $contract->id,
                'contract_title' => $contract->title,
                'brand_id' => $contract->brand_id,
                'brand_name' => $contract->brand->name,
                'completed_at' => $contract->completed_at->toISOString(),
            ]);
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($contract->creator_id, $notification);
            
        } catch (\Exception $e) {
            Log::error('Failed to notify creator of contract completion', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to user about new review
     */
    public static function notifyUserOfNewReview($review): void
    {
        try {
            $notification = Notification::createNewReview($review->reviewed_id, [
                'review_id' => $review->id,
                'contract_id' => $review->contract_id,
                'contract_title' => $review->contract->title,
                'reviewer_id' => $review->reviewer_id,
                'reviewer_name' => $review->reviewer->name,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at->toISOString(),
            ]);
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($review->reviewed_id, $notification);
            
        } catch (\Exception $e) {
            Log::error('Failed to notify user of new review', [
                'review_id' => $review->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to user about contract termination
     */
    public static function notifyUserOfContractTerminated($contract, string $reason = null): void
    {
        try {
            // Notify both brand and creator
            $users = [$contract->brand_id, $contract->creator_id];
            
            foreach ($users as $userId) {
                $notification = Notification::createContractTerminated($userId, [
                    'contract_id' => $contract->id,
                    'contract_title' => $contract->title,
                    'terminated_at' => $contract->cancelled_at->toISOString(),
                    'reason' => $reason,
                ]);
                
                // Send real-time notification via Socket.IO
                self::sendSocketNotification($userId, $notification);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to notify users of contract termination', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to creator about payment available
     */
    public static function notifyCreatorOfPaymentAvailable($contract): void
    {
        try {
            $notification = Notification::createPaymentAvailable($contract->creator_id, [
                'contract_id' => $contract->id,
                'contract_title' => $contract->title,
                'brand_id' => $contract->brand_id,
                'brand_name' => $contract->brand->name,
                'creator_amount' => $contract->creator_amount,
                'platform_fee' => $contract->platform_fee,
                'payment_available_at' => now()->toISOString(),
            ]);
            
            // Send real-time notification via Socket.IO
            self::sendSocketNotification($contract->creator_id, $notification);
            
        } catch (\Exception $e) {
            Log::error('Failed to notify creator of payment available', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage()
            ]);
        }
    }
} 