<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Campaign\CampaignController;
use App\Http\Controllers\Campaign\BidController;
use App\Http\Controllers\CampaignApplicationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BrandProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\CreatorBalanceController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\PostContractWorkflowController;
use App\Http\Controllers\AdminPayoutController;
use App\Http\Controllers\BrandPaymentController;
use App\Http\Controllers\CampaignTimelineController;
use App\Http\Controllers\ContractPaymentController;
use App\Http\Controllers\GuideController;

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Nexa API is running',
        'timestamp' => now()->toISOString()
    ]);
});

// Include auth routes
require __DIR__.'/auth.php';

// File download route with CORS headers
Route::get('/download/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    
    if (!file_exists($filePath)) {
        return response()->json(['error' => 'File not found'], 404);
    }
    
    $file = new \Illuminate\Http\File($filePath);
    $mimeType = $file->getMimeType();
    $fileName = basename($path);
    
    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
        'Access-Control-Allow-Credentials' => 'true',
    ]);
})->where('path', '.*');


Route::middleware(['auth:sanctum', 'throttle:user-status'])->get('/user', function (Request $request) {
    return $request->user();
});

// Public guide routes (read-only, no authentication required)
Route::get('/guides', [GuideController::class, 'index']);                 // Get all guides
Route::get('/guides/{guide}', [GuideController::class, 'show']);          // Get a single guide by ID



// Authenticated user routes
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Profile management
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']); // Get current user profile
        Route::put('/', [ProfileController::class, 'update']); // Update current user profile

    });
    
    // Brand Profile management
    Route::prefix('brand-profile')->group(function () {
        Route::get('/', [BrandProfileController::class, 'show']); // Get brand profile
        Route::put('/', [BrandProfileController::class, 'update']); // Update brand profile
        Route::post('/change-password', [BrandProfileController::class, 'changePassword']); // Change password
        Route::post('/avatar', [BrandProfileController::class, 'uploadAvatar']); // Upload avatar
        Route::delete('/avatar', [BrandProfileController::class, 'deleteAvatar']); // Delete avatar
    });

    // Campaign CRUD operations (require premium for creators)
    Route::prefix('campaigns')->middleware(['premium.access'])->group(function () {
        Route::get('/', [CampaignController::class, 'index']); // List campaigns
        Route::get('/get-campaigns', [CampaignController::class, 'getCampaigns']); // Get campaigns with advanced filtering
        Route::get('/get-all-campaigns', [CampaignController::class, 'getAllCampaigns']); // Get all campaigns without pagination
        Route::get('/pending', [CampaignController::class, 'getPendingCampaigns']); // Get pending campaigns
        Route::get('/user/{user}', [CampaignController::class, 'getUserCampaigns']); // Get campaigns by user
        Route::get('/status/{status}', [CampaignController::class, 'getCampaignsByStatus']); // Get campaigns by status
        Route::post('/', [CampaignController::class, 'store']); // Create campaign (Brand only)
        Route::get('/statistics', [CampaignController::class, 'statistics']); // Get statistics
        Route::get('/favorites', [CampaignController::class, 'getFavorites']); // Get favorite campaigns (Creator only)
        
        // Specific campaign operations (must come before {campaign} route)
        Route::patch('/{campaign}/approve', [CampaignController::class, 'approve']); // Approve campaign (Admin only)
        Route::patch('/{campaign}/reject', [CampaignController::class, 'reject']); // Reject campaign (Admin only)
        Route::patch('/{campaign}/archive', [CampaignController::class, 'archive']); // Archive campaign (Admin/Brand only)
        Route::patch('/{campaign}/toggle-featured', [CampaignController::class, 'toggleFeatured']); // Toggle featured status (Admin only)
        Route::post('/{campaign}/toggle-active', [CampaignController::class, 'toggleActive']); // Toggle active status (Brand only)
        Route::post('/{campaign}/toggle-favorite', [CampaignController::class, 'toggleFavorite']); // Toggle favorite status (Creator only)
        Route::get('/{campaign}/bids', [BidController::class, 'campaignBids']); // Get bids for campaign
        
        // Generic campaign routes (must come after specific routes)
        Route::get('/{campaign}', [CampaignController::class, 'show']); // View campaign
        Route::put('/{campaign}', [CampaignController::class, 'update']); // Update campaign (Brand only)
        Route::delete('/{campaign}', [CampaignController::class, 'destroy']); // Delete campaign (Brand only)
    });
    
    // Bid CRUD operations (require premium for creators)
    Route::prefix('bids')->middleware(['premium.access'])->group(function () {
        Route::get('/', [BidController::class, 'index']); // List bids
        Route::get('/{bid}', [BidController::class, 'show']); // View bid
        Route::put('/{bid}', [BidController::class, 'update']); // Update bid (Creator only)
        Route::delete('/{bid}', [BidController::class, 'destroy']); // Delete bid (Creator only)
        
        // Brand operations
        Route::post('/{bid}/accept', [BidController::class, 'accept']); // Accept bid (Brand only)
        Route::post('/{bid}/reject', [BidController::class, 'reject']); // Reject bid (Brand only)
        
        // Creator operations
        Route::post('/{bid}/withdraw', [BidController::class, 'withdraw']); // Withdraw bid (Creator only)
    });
    
    // Create bid on campaign (require premium for creators)
    Route::post('/campaigns/{campaign}/bids', [BidController::class, 'store'])->middleware(['premium.access']); // Create bid on campaign (Creator only)
    
    // Campaign Application CRUD operations (require premium for creators)
    Route::prefix('applications')->middleware(['premium.access'])->group(function () {
        Route::get('/', [CampaignApplicationController::class, 'index']); // List applications (role-based)
        Route::get('/statistics', [CampaignApplicationController::class, 'statistics']); // Get application statistics
        Route::get('/{application}', [CampaignApplicationController::class, 'show']); // View application
        Route::post('/{application}/approve', [CampaignApplicationController::class, 'approve']); // Approve application (Brand only)
        Route::post('/{application}/reject', [CampaignApplicationController::class, 'reject']); // Reject application (Brand only)
        Route::delete('/{application}/withdraw', [CampaignApplicationController::class, 'withdraw']); // Withdraw application (Creator only)
    });
    
    // Create application on campaign (require premium for creators)
    Route::post('/campaigns/{campaign}/applications', [CampaignApplicationController::class, 'store'])->middleware(['premium.access']); // Create application (Creator only)
    
    // Get applications for a specific campaign (require premium for creators)
    Route::get('/campaigns/{campaign}/applications', [CampaignApplicationController::class, 'campaignApplications'])->middleware(['premium.access']); // Get campaign applications
    
    // Chat room management (require premium for creators)
    Route::prefix('chat')->middleware(['premium.access'])->group(function () {
        Route::get('/rooms', [ChatController::class, 'getChatRooms']); // Get user's chat rooms
        Route::get('/rooms/{roomId}/messages', [ChatController::class, 'getMessages']); // Get messages for a room
        Route::post('/rooms', [ChatController::class, 'createChatRoom']); // Create chat room (brand accepts proposal)
        Route::post('/messages', [ChatController::class, 'sendMessage']); // Send a message
        Route::post('/mark-read', [ChatController::class, 'markMessagesAsRead']); // Mark messages as read
        Route::post('/typing-status', [ChatController::class, 'updateTypingStatus']); // Update typing status
        Route::post('/rooms/{roomId}/send-guide-messages', [ChatController::class, 'sendGuideMessages']); // Send guide messages when user first enters chat
    });
    
    // Connection management (require premium for creators)
    Route::prefix('connections')->middleware(['premium.access'])->group(function () {
        Route::post('/send-request', [ConnectionController::class, 'sendConnectionRequest']); // Send connection request
        Route::post('/{requestId}/accept', [ConnectionController::class, 'acceptConnectionRequest']); // Accept connection request
        Route::post('/{requestId}/reject', [ConnectionController::class, 'rejectConnectionRequest']); // Reject connection request
        Route::post('/{requestId}/cancel', [ConnectionController::class, 'cancelConnectionRequest']); // Cancel connection request
        Route::get('/requests', [ConnectionController::class, 'getConnectionRequests']); // Get connection requests
        Route::get('/search-creators', [ConnectionController::class, 'searchCreators']); // Search for creators (brands only)
    });
    
    // Direct chat management (require premium for creators)
    Route::prefix('direct-chat')->middleware(['premium.access'])->group(function () {
        Route::get('/rooms', [ConnectionController::class, 'getDirectChatRooms']); // Get direct chat rooms
        Route::get('/rooms/{roomId}/messages', [ConnectionController::class, 'getDirectMessages']); // Get direct messages
        Route::post('/messages', [ConnectionController::class, 'sendDirectMessage']); // Send direct message
    });
});

// Notification routes (available to all authenticated users)
Route::middleware(['auth:sanctum', 'throttle:notifications'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::get('/notifications/statistics', [NotificationController::class, 'statistics']);
    
});

// Portfolio routes
Route::middleware(['auth:sanctum', 'premium.access'])->group(function () {
    Route::get('/portfolio', [PortfolioController::class, 'show']);
    Route::put('/portfolio/profile', [PortfolioController::class, 'updateProfile']);
    Route::post('/portfolio/media', [PortfolioController::class, 'uploadMedia']);
    Route::put('/portfolio/items/{item}', [PortfolioController::class, 'updateItem']);
    Route::delete('/portfolio/items/{item}', [PortfolioController::class, 'deleteItem']);
    Route::post('/portfolio/reorder', [PortfolioController::class, 'reorderItems']);
    Route::get('/portfolio/statistics', [PortfolioController::class, 'statistics']);
});

// Creator profile for brands (public view)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/creators/{creatorId}/profile', [PortfolioController::class, 'getCreatorProfile']);
});

// Payment routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/payment/methods', [PaymentController::class, 'getPaymentMethods']);
    Route::post('/payment/methods', [PaymentController::class, 'createPaymentMethod']);
    Route::delete('/payment/methods/{cardId}', [PaymentController::class, 'deletePaymentMethod']);
    Route::post('/payment/process', [PaymentController::class, 'processPayment']);
    Route::get('/payment/history', [PaymentController::class, 'getPaymentHistory']);
    
    // Subscription routes
    Route::middleware(['throttle:payment'])->group(function () {
        Route::post('/payment/subscription', [PaymentController::class, 'processSubscription']);
        Route::get('/payment/subscription-status', [PaymentController::class, 'getSubscriptionStatus']);
    });
    Route::get('/payment/transactions', [PaymentController::class, 'getTransactionHistory']);
    
    // Freelancer payment routes
    Route::prefix('freelancer')->group(function () {
        // Bank account management
        Route::post('/register-bank', [PaymentController::class, 'registerBankAccount']); // Register bank account
        Route::get('/bank-info', [PaymentController::class, 'getBankInfo']); // Get bank info
        Route::put('/bank-info', [PaymentController::class, 'updateBankInfo']); // Update bank info
        Route::delete('/bank-info', [PaymentController::class, 'deleteBankInfo']); // Delete bank info
        
        // Withdrawal management
        Route::get('/withdrawals', [PaymentController::class, 'getWithdrawalHistory']); // Get withdrawal history
        Route::post('/withdrawals', [PaymentController::class, 'requestWithdrawal']); // Request withdrawal
        
        // Earnings and balance
        Route::get('/earnings', [PaymentController::class, 'getEarnings']); // Get earnings and balance
        Route::get('/withdrawal-methods', [PaymentController::class, 'getWithdrawalMethods']); // Get available withdrawal methods
    });

    // Brand payment methods (for contract payments)
    Route::prefix('brand-payment')->group(function () {
        Route::post('/save-method', [BrandPaymentController::class, 'savePaymentMethod']);
        Route::get('/methods', [BrandPaymentController::class, 'getPaymentMethods']);
        Route::post('/set-default', [BrandPaymentController::class, 'setDefaultPaymentMethod']);
        Route::delete('/methods', [BrandPaymentController::class, 'deletePaymentMethod']);
    });

    // Contract payment processing
    Route::prefix('contract-payment')->group(function () {
        Route::post('/process', [ContractPaymentController::class, 'processContractPayment']);
        Route::get('/status', [ContractPaymentController::class, 'getContractPaymentStatus']);
        Route::get('/methods', [ContractPaymentController::class, 'getAvailablePaymentMethods']);
        Route::post('/retry', [ContractPaymentController::class, 'retryPayment']);
    });
    
    // Offer routes
    Route::prefix('offers')->group(function () {
        Route::post('/', [OfferController::class, 'store']); // Create offer
        Route::get('/', [OfferController::class, 'index']); // Get offers
        Route::get('/{id}', [OfferController::class, 'show']); // Get specific offer
        Route::post('/{id}/accept', [OfferController::class, 'accept']); // Accept offer
        Route::post('/{id}/reject', [OfferController::class, 'reject']); // Reject offer
        Route::delete('/{id}', [OfferController::class, 'cancel']); // Cancel offer
        Route::get('/chat-room/{roomId}', [OfferController::class, 'getOffersForChatRoom']); // Get offers for chat room
    });
    
    // Contract routes
    Route::prefix('contracts')->group(function () {
        Route::get('/', [ContractController::class, 'index']); // Get contracts
        Route::get('/{id}', [ContractController::class, 'show']); // Get specific contract
        Route::get('/chat-room/{roomId}', [ContractController::class, 'getContractsForChatRoom']); // Get contracts for chat room
        Route::post('/{id}/activate', [ContractController::class, 'activate']); // Activate contract
        Route::post('/{id}/complete', [ContractController::class, 'complete']); // Complete contract
        Route::post('/{id}/cancel', [ContractController::class, 'cancel']); // Cancel contract
        Route::post('/{id}/terminate', [ContractController::class, 'terminate']); // Terminate contract (brand only)
        Route::post('/{id}/dispute', [ContractController::class, 'dispute']); // Dispute contract
    });
    
    // Campaign Timeline routes
    Route::prefix('campaign-timeline')->group(function () {
        Route::get('/', [CampaignTimelineController::class, 'index']); // Get timeline for contract
        Route::post('/create-milestones', [CampaignTimelineController::class, 'createMilestones']); // Create milestones for contract
        Route::post('/upload-file', [CampaignTimelineController::class, 'uploadFile']); // Upload file for milestone
        Route::post('/approve-milestone', [CampaignTimelineController::class, 'approveMilestone']); // Approve milestone
        Route::post('/complete-milestone', [CampaignTimelineController::class, 'completeMilestone']); // Complete milestone
        Route::post('/justify-delay', [CampaignTimelineController::class, 'justifyDelay']); // Justify delay
        Route::post('/mark-delayed', [CampaignTimelineController::class, 'markAsDelayed']); // Mark as delayed
        Route::post('/extend-timeline', [CampaignTimelineController::class, 'extendTimeline']); // Extend timeline
        Route::get('/download-file', [CampaignTimelineController::class, 'downloadFile']); // Download file
        Route::get('/statistics', [CampaignTimelineController::class, 'getStatistics']); // Get timeline statistics
    });
    
    // Review routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::get('/reviews', [ReviewController::class, 'index']);
        Route::get('/reviews/{id}', [ReviewController::class, 'show']);
        Route::put('/reviews/{id}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
        Route::get('/contracts/{contractId}/review-status', [ReviewController::class, 'getContractReviewStatus']);
    });
    
    // Creator balance routes
    Route::prefix('creator-balance')->group(function () {
        Route::get('/', [CreatorBalanceController::class, 'index']); // Get balance
        Route::get('/history', [CreatorBalanceController::class, 'history']); // Get balance history
        Route::get('/withdrawal-methods', [CreatorBalanceController::class, 'withdrawalMethods']); // Get withdrawal methods
        Route::get('/work-history', [CreatorBalanceController::class, 'workHistory']); // Get work history
    });
    
    // Withdrawal routes
    Route::prefix('withdrawals')->group(function () {
        Route::post('/', [WithdrawalController::class, 'store']); // Create withdrawal
        Route::get('/', [WithdrawalController::class, 'index']); // Get withdrawals
        Route::get('/{id}', [WithdrawalController::class, 'show']); // Get specific withdrawal
        Route::delete('/{id}', [WithdrawalController::class, 'cancel']); // Cancel withdrawal
        Route::get('/statistics', [WithdrawalController::class, 'statistics']); // Get withdrawal statistics
    });
    
    // Post-contract workflow routes
    Route::prefix('post-contract')->group(function () {
        Route::get('/waiting-review', [PostContractWorkflowController::class, 'getContractsWaitingForReview']); // Get contracts waiting for review
        Route::get('/payment-available', [PostContractWorkflowController::class, 'getContractsWithPaymentAvailable']); // Get contracts with payment available
        Route::get('/work-history', [PostContractWorkflowController::class, 'getWorkHistory']); // Get work history
    });
    
    // Public guide routes (read-only for authenticated users)
    // Route::get('/guides', [GuideController::class, 'index']);                 // Get all guides
    // Route::get('/guides/{guide}', [GuideController::class, 'show']);          // Get a single guide by ID
});

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Dashboard endpoints
    Route::get('/dashboard-metrics', [AdminController::class, 'getDashboardMetrics']);
    Route::get('/pending-campaigns', [AdminController::class, 'getPendingCampaigns']);
    Route::get('/recent-users', [AdminController::class, 'getRecentUsers']);
    
    // Campaign management
    Route::get('/campaigns', [AdminController::class, 'getCampaigns']);
    Route::get('/campaigns/{id}', [AdminController::class, 'getCampaign']);
    Route::patch('/campaigns/{id}/approve', [AdminController::class, 'approveCampaign']);
    Route::patch('/campaigns/{id}/reject', [AdminController::class, 'rejectCampaign']);
    Route::delete('/campaigns/{id}', [AdminController::class, 'deleteCampaign']);
    
    // User management
    Route::get('/users', [AdminController::class, 'getUsers']);
    Route::get('/users/creators', [AdminController::class, 'getCreators']);
    Route::get('/users/brands', [AdminController::class, 'getBrands']);
    Route::get('/users/statistics', [AdminController::class, 'getUserStatistics']);
    Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus']);
    
    // Withdrawal methods management
    Route::apiResource('withdrawal-methods', \App\Http\Controllers\Admin\WithdrawalMethodController::class);
    Route::put('/withdrawal-methods/{id}/toggle-active', [\App\Http\Controllers\Admin\WithdrawalMethodController::class, 'toggleActive']);
    
    // Payout management
    Route::get('/payouts/pending', [AdminPayoutController::class, 'getPendingWithdrawals']);
    Route::post('/payouts/{id}/process', [AdminPayoutController::class, 'processWithdrawal']);
    Route::get('/payouts/verification-report', [AdminPayoutController::class, 'getWithdrawalVerificationReport']);
    Route::get('/payouts/{id}/verify', [AdminPayoutController::class, 'verifyWithdrawal']);

    // Guide Management
    Route::get('/guides', [GuideController::class, 'index']);                 // Get all guides
    Route::post('/guides', [GuideController::class, 'store']);                // Create a new guide
    Route::get('/guides/{guide}', [GuideController::class, 'show']);          // Get a single guide by ID (route model binding)
    Route::put('/guides/{guide}', [GuideController::class, 'update']);        // Update a guide by ID
    Route::delete('/guides/{guide}', [GuideController::class, 'destroy']);    // Delete a guide by ID
});



// Google OAuth routes
Route::get('/google/redirect', [GoogleController::class, 'redirectToGoogle'])
    ->name('google.redirect');

Route::get('/google/callback', [GoogleController::class, 'handleGoogleCallback'])
    ->name('google.callback');



Route::post('/google/auth', [GoogleController::class, 'handleGoogleWithRole'])
    ->name('google.auth');



// Auth routes
require __DIR__.'/auth.php';
