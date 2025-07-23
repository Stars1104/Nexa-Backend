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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

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

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Registration endpoint: POST /api/register
| Expected payload: { "name": "John Doe", "email": "user@example.com", "password": "password", "password_confirmation": "password", "role": "creator" }
| Returns: { "success": true, "token": "...", "token_type": "Bearer", "user": {...} }
|
| Login endpoint: POST /api/login
| Expected payload: { "email": "user@example.com", "password": "password" }
| Returns: { "success": true, "token": "...", "token_type": "Bearer", "user": {...} }
|
| Usage: Include token in Authorization header: "Bearer YOUR_TOKEN_HERE"
|
*/

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Protected routes - require authentication
Route::middleware(['auth:sanctum'])->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Profile Routes
    |--------------------------------------------------------------------------
    |
    | User profile management endpoints for getting and updating user profiles.
    |
    */
    
    // Profile management
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']); // Get current user profile
        Route::put('/', [ProfileController::class, 'update']); // Update current user profile
        Route::post('/test-formdata', [ProfileController::class, 'testFormData']); // Test FormData parsing
    });
    
    // Brand Profile management
    Route::prefix('brand-profile')->group(function () {
        Route::get('/', [BrandProfileController::class, 'show']); // Get brand profile
        Route::put('/', [BrandProfileController::class, 'update']); // Update brand profile
        Route::post('/change-password', [BrandProfileController::class, 'changePassword']); // Change password
        Route::post('/avatar', [BrandProfileController::class, 'uploadAvatar']); // Upload avatar
        Route::delete('/avatar', [BrandProfileController::class, 'deleteAvatar']); // Delete avatar
    });
    
    /*
    |--------------------------------------------------------------------------
    | Campaign Routes
    |--------------------------------------------------------------------------
    |
    | Campaign management endpoints for brands, creators, and admins.
    | Different roles have different permissions and access levels.
    |
    */
    
    // Campaign CRUD operations
    Route::prefix('campaigns')->group(function () {
        Route::get('/', [CampaignController::class, 'index']); // List campaigns
        Route::get('/get-campaigns', [CampaignController::class, 'getCampaigns']); // Get campaigns with advanced filtering
        Route::get('/get-all-campaigns', [CampaignController::class, 'getAllCampaigns']); // Get all campaigns without pagination
        Route::get('/pending', [CampaignController::class, 'getPendingCampaigns']); // Get pending campaigns
        Route::get('/user/{user}', [CampaignController::class, 'getUserCampaigns']); // Get campaigns by user
        Route::get('/status/{status}', [CampaignController::class, 'getCampaignsByStatus']); // Get campaigns by status
        Route::post('/', [CampaignController::class, 'store']); // Create campaign (Brand only)
        Route::get('/statistics', [CampaignController::class, 'statistics']); // Get statistics
        Route::get('/{campaign}', [CampaignController::class, 'show']); // View campaign
        Route::put('/{campaign}', [CampaignController::class, 'update']); // Update campaign (Brand only)
        Route::delete('/{campaign}', [CampaignController::class, 'destroy']); // Delete campaign (Brand only)
        
        // Admin operations
        Route::patch('/{campaign}/approve', [CampaignController::class, 'approve']); // Approve campaign (Admin only)
        Route::patch('/{campaign}/reject', [CampaignController::class, 'reject']); // Reject campaign (Admin only)
        Route::patch('/{campaign}/archive', [CampaignController::class, 'archive']); // Archive campaign (Admin/Brand only)
        
        // Brand operations
        Route::post('/{campaign}/toggle-active', [CampaignController::class, 'toggleActive']); // Toggle active status (Brand only)
        
        // Campaign bids
        Route::get('/{campaign}/bids', [BidController::class, 'campaignBids']); // Get bids for campaign
    });
    
    /*
    |--------------------------------------------------------------------------
    | Bid Routes
    |--------------------------------------------------------------------------
    |
    | Bidding system endpoints for creators to bid on campaigns and
    | brands to manage bids on their campaigns.
    |
    */
    
    // Bid CRUD operations
    Route::prefix('bids')->group(function () {
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
    
    // Create bid on campaign
    Route::post('/campaigns/{campaign}/bids', [BidController::class, 'store']); // Create bid on campaign (Creator only)
    
    /*
    |--------------------------------------------------------------------------
    | Campaign Application Routes
    |--------------------------------------------------------------------------
    |
    | Application system endpoints for creators to apply to campaigns and
    | brands to review applications.
    |
    */
    
    // Campaign Application CRUD operations
    Route::prefix('applications')->group(function () {
        Route::get('/', [CampaignApplicationController::class, 'index']); // List applications (role-based)
        Route::get('/statistics', [CampaignApplicationController::class, 'statistics']); // Get application statistics
        Route::get('/{application}', [CampaignApplicationController::class, 'show']); // View application
        Route::post('/{application}/approve', [CampaignApplicationController::class, 'approve']); // Approve application (Brand only)
        Route::post('/{application}/reject', [CampaignApplicationController::class, 'reject']); // Reject application (Brand only)
        Route::delete('/{application}/withdraw', [CampaignApplicationController::class, 'withdraw']); // Withdraw application (Creator only)
    });
    
    // Create application on campaign
    Route::post('/campaigns/{campaign}/applications', [CampaignApplicationController::class, 'store']); // Create application (Creator only)
    
    // Get applications for a specific campaign
    Route::get('/campaigns/{campaign}/applications', [CampaignApplicationController::class, 'campaignApplications']); // Get campaign applications
    
    /*
    |--------------------------------------------------------------------------
    | Chat Routes
    |--------------------------------------------------------------------------
    |
    | Real-time chat system endpoints for communication between brands and creators.
    |
    */
    
    // Chat room management
    Route::prefix('chat')->group(function () {
        Route::get('/rooms', [ChatController::class, 'getChatRooms']); // Get user's chat rooms
        Route::get('/rooms/{roomId}/messages', [ChatController::class, 'getMessages']); // Get messages for a room
        Route::post('/rooms', [ChatController::class, 'createChatRoom']); // Create chat room (brand accepts proposal)
        Route::post('/messages', [ChatController::class, 'sendMessage']); // Send a message
        Route::post('/mark-read', [ChatController::class, 'markMessagesAsRead']); // Mark messages as read
        Route::post('/typing-status', [ChatController::class, 'updateTypingStatus']); // Update typing status
    });
    
    /*
    |--------------------------------------------------------------------------
    | Connection Routes
    |--------------------------------------------------------------------------
    |
    | Direct connection system for brands and creators to connect and chat.
    |
    */
    
    // Connection management
    Route::prefix('connections')->group(function () {
        Route::post('/send-request', [ConnectionController::class, 'sendConnectionRequest']); // Send connection request
        Route::post('/{requestId}/accept', [ConnectionController::class, 'acceptConnectionRequest']); // Accept connection request
        Route::post('/{requestId}/reject', [ConnectionController::class, 'rejectConnectionRequest']); // Reject connection request
        Route::post('/{requestId}/cancel', [ConnectionController::class, 'cancelConnectionRequest']); // Cancel connection request
        Route::get('/requests', [ConnectionController::class, 'getConnectionRequests']); // Get connection requests
        Route::get('/search-creators', [ConnectionController::class, 'searchCreators']); // Search for creators (brands only)
    });
    
    // Direct chat management
    Route::prefix('direct-chat')->group(function () {
        Route::get('/rooms', [ConnectionController::class, 'getDirectChatRooms']); // Get direct chat rooms
        Route::get('/rooms/{roomId}/messages', [ConnectionController::class, 'getDirectMessages']); // Get direct messages
        Route::post('/messages', [ConnectionController::class, 'sendDirectMessage']); // Send direct message
    });
    
    /*
    |--------------------------------------------------------------------------
    | API Documentation Routes
    |--------------------------------------------------------------------------
    |
    | Campaign API Examples:
    |
    | GET /api/campaigns - List campaigns (role-based filtering)
    | POST /api/campaigns - Create campaign (brands only)
    | GET /api/campaigns/{id} - View campaign details
    | PUT /api/campaigns/{id} - Update campaign (brand owner only)
    | DELETE /api/campaigns/{id} - Delete campaign (brand owner only)
    | POST /api/campaigns/{id}/approve - Approve campaign (admin only)
    | POST /api/campaigns/{id}/reject - Reject campaign (admin only)
    | POST /api/campaigns/{id}/toggle-active - Toggle campaign status (brand owner only)
    | GET /api/campaigns/statistics - Get campaign statistics
    |
    | Bid API Examples:
    |
    | GET /api/bids - List bids (role-based filtering)
    | POST /api/campaigns/{id}/bids - Create bid on campaign (creators only)
    | GET /api/bids/{id} - View bid details
    | PUT /api/bids/{id} - Update bid (bid owner only)
    | DELETE /api/bids/{id} - Delete bid (bid owner only)
    | POST /api/bids/{id}/accept - Accept bid (campaign owner only)
    | POST /api/bids/{id}/reject - Reject bid (campaign owner only)
    | POST /api/bids/{id}/withdraw - Withdraw bid (bid owner only)
    | GET /api/campaigns/{id}/bids - Get campaign bids
    |
    | Campaign Application API Examples:
    |
    | GET /api/applications - List applications (role-based filtering)
    | POST /api/campaigns/{id}/applications - Create application on campaign (creators only)
    | GET /api/applications/{id} - View application details
    | POST /api/applications/{id}/approve - Approve application (brand owner only)
    | POST /api/applications/{id}/reject - Reject application (brand owner only)
    | DELETE /api/applications/{id}/withdraw - Withdraw application (application owner only)
    | GET /api/campaigns/{id}/applications - Get applications for campaign
    | GET /api/applications/statistics - Get application statistics
    |
    | Notification Routes:
    | GET /api/notifications - Get user notifications
    | GET /api/notifications/unread-count - Get unread notification count
    | POST /api/notifications/{id}/mark-read - Mark notification as read
    | POST /api/notifications/mark-all-read - Mark all notifications as read
    | DELETE /api/notifications/{id} - Delete notification
    | GET /api/notifications/statistics - Get notification statistics
    |
    */
});

// Notification routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::get('/notifications/statistics', [NotificationController::class, 'statistics']);
    Route::post('/notifications/test', [NotificationController::class, 'testNotification']); // Test notification endpoint
});

// Portfolio routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/portfolio', [PortfolioController::class, 'show']);
    Route::put('/portfolio/profile', [PortfolioController::class, 'updateProfile']);
    Route::post('/portfolio/media', [PortfolioController::class, 'uploadMedia']);
    Route::put('/portfolio/items/{item}', [PortfolioController::class, 'updateItem']);
    Route::delete('/portfolio/items/{item}', [PortfolioController::class, 'deleteItem']);
    Route::post('/portfolio/reorder', [PortfolioController::class, 'reorderItems']);
    Route::get('/portfolio/statistics', [PortfolioController::class, 'statistics']);
});

// Payment routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/payment/methods', [PaymentController::class, 'getPaymentMethods']);
    Route::post('/payment/methods', [PaymentController::class, 'createPaymentMethod']);
    Route::delete('/payment/methods/{cardId}', [PaymentController::class, 'deletePaymentMethod']);
    Route::post('/payment/process', [PaymentController::class, 'processPayment']);
    Route::get('/payment/history', [PaymentController::class, 'getPaymentHistory']);
});

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::prefix('admin')->group(function () {
        // Dashboard endpoints
        Route::get('/dashboard-metrics', [AdminController::class, 'getDashboardMetrics']);
        Route::get('/pending-campaigns', [AdminController::class, 'getPendingCampaigns']);
        Route::get('/recent-users', [AdminController::class, 'getRecentUsers']);
        
        // User management endpoints
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::get('/users/creators', [AdminController::class, 'getCreators']);
        Route::get('/users/brands', [AdminController::class, 'getBrands']);
        Route::get('/users/statistics', [AdminController::class, 'getUserStatistics']);
        Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus']);
    });
});


// Test endpoint for debugging login issues
Route::get('/test-auth', function () {
    return [
        'authenticated' => Auth::check(),
        'user' => Auth::user(),
        'guard' => Auth::getDefaultDriver(),
        'guards' => array_keys(config('auth.guards')),
    ];
});

// Google OAuth routes
Route::get('/google/redirect', [GoogleController::class, 'redirectToGoogle'])
    ->name('google.redirect');

Route::get('/google/callback', [GoogleController::class, 'handleGoogleCallback'])
    ->name('google.callback');

Route::get('/google/test-callback', [GoogleController::class, 'testCallback'])
    ->name('google.test-callback');

Route::post('/google/auth', [GoogleController::class, 'handleGoogleWithRole'])
    ->name('google.auth');

// Debug endpoint to test login with specific credentials
Route::post('/debug-login', function (Request $request) {
    $email = $request->input('email');
    $password = $request->input('password');
    
    \Log::info('Debug login attempt', [
        'email' => $email,
        'password_provided' => !empty($password),
        'headers' => $request->headers->all(),
        'user_agent' => $request->userAgent(),
        'ip' => $request->ip(),
    ]);
    
    $user = \App\Models\User::where('email', $email)->first();
    
    if (!$user) {
        return response()->json([
            'error' => 'User not found',
            'email' => $email
        ], 422);
    }
    
    if (!Hash::check($password, $user->password)) {
        return response()->json([
            'error' => 'Password incorrect',
            'email' => $email,
            'password_hash' => $user->password
        ], 422);
    }
    
    return response()->json([
        'success' => true,
        'user_found' => true,
        'password_correct' => true,
        'user_id' => $user->id,
        'user_name' => $user->name
    ]);
});






// Auth routes
require __DIR__.'/auth.php';
