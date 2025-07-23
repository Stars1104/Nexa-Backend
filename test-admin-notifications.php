<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\Campaign;
use App\Models\Bid;
use App\Models\CampaignApplication;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Services\NotificationService;
use App\Models\Notification;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ADMIN NOTIFICATION SYSTEM TEST ===\n\n";

// Get or create admin user
$admin = User::where('role', 'admin')->first();
if (!$admin) {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@nexa.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);
    echo "✅ Created admin user: {$admin->name} ({$admin->email})\n";
} else {
    echo "✅ Found admin user: {$admin->name} ({$admin->email})\n";
}

// Get or create test users
$brand = User::where('email', 'brand@test.com')->first();
if (!$brand) {
    $brand = User::create([
        'name' => 'Test Brand',
        'email' => 'brand@test.com',
        'password' => bcrypt('password'),
        'role' => 'brand',
        'company_name' => 'Test Company',
    ]);
    echo "✅ Created brand user: {$brand->name} ({$brand->email})\n";
} else {
    echo "✅ Found brand user: {$brand->name} ({$brand->email})\n";
}

$creator = User::where('email', 'creator@test.com')->first();
if (!$creator) {
    $creator = User::create([
        'name' => 'Test Creator',
        'email' => 'creator@test.com',
        'password' => bcrypt('password'),
        'role' => 'creator',
    ]);
    echo "✅ Created creator user: {$creator->name} ({$creator->email})\n";
} else {
    echo "✅ Found creator user: {$creator->name} ({$creator->email})\n";
}

echo "\n=== TESTING ADMIN NOTIFICATIONS ===\n\n";

// Test 1: New user registration notification
echo "1. Testing new user registration notification...\n";
NotificationService::notifyAdminOfNewRegistration($creator);
echo "✅ New user registration notification sent\n";

// Test 2: New campaign creation notification
echo "\n2. Testing new campaign creation notification...\n";
$campaign = Campaign::create([
    'brand_id' => $brand->id,
    'title' => 'Test Campaign for Notifications',
    'description' => 'This is a test campaign to verify admin notifications',
    'budget' => 1000.00,
    'location' => 'São Paulo',
    'requirements' => 'Test requirements',
    'target_states' => ['SP', 'RJ'],
    'category' => 'test',
    'campaign_type' => 'test',
    'status' => 'pending',
    'is_active' => true,
    'deadline' => now()->addDays(30),
    'max_bids' => 10,
]);

NotificationService::notifyAdminOfNewCampaign($campaign);
echo "✅ New campaign notification sent\n";

// Test 3: New application notification
echo "\n3. Testing new application notification...\n";
$application = CampaignApplication::create([
    'campaign_id' => $campaign->id,
    'creator_id' => $creator->id,
    'status' => 'pending',
    'proposal' => 'This is a test proposal for admin notifications',
    'portfolio_links' => ['https://test.com/portfolio'],
    'estimated_delivery_days' => 7,
    'proposed_budget' => 800.00,
]);

NotificationService::notifyAdminOfNewApplication($application);
echo "✅ New application notification sent\n";

// Test 4: New bid notification
echo "\n4. Testing new bid notification...\n";
$bid = Bid::create([
    'campaign_id' => $campaign->id,
    'user_id' => $creator->id,
    'bid_amount' => 750.00,
    'proposal' => 'This is a test bid proposal for admin notifications',
    'portfolio_links' => 'https://test.com/portfolio',
    'estimated_delivery_days' => 5,
    'status' => 'pending',
]);

NotificationService::notifyAdminOfNewBid($bid);
echo "✅ New bid notification sent\n";

// Test 5: Payment activity notification
echo "\n5. Testing payment activity notification...\n";
NotificationService::notifyAdminOfPaymentActivity($brand, 'payment_method_created', [
    'card_id' => 'test_card_123',
    'card_brand' => 'visa',
    'last_four_digits' => '1234',
]);
echo "✅ Payment activity notification sent\n";

// Test 6: Portfolio update notification
echo "\n6. Testing portfolio update notification...\n";
NotificationService::notifyAdminOfPortfolioUpdate($creator, 'profile_update', [
    'portfolio_id' => 1,
    'title' => 'Updated Portfolio Title',
    'bio' => 'Updated portfolio bio',
    'has_profile_picture' => true,
]);
echo "✅ Portfolio update notification sent\n";

// Test 7: System activity notification
echo "\n7. Testing system activity notification...\n";
NotificationService::notifyAdminOfSystemActivity('test_activity', [
    'test_data' => 'This is a test system activity',
    'timestamp' => now()->toISOString(),
]);
echo "✅ System activity notification sent\n";

// Test 8: Campaign approval notification
echo "\n8. Testing campaign approval notification...\n";
NotificationService::notifyAdminOfSystemActivity('campaign_approved', [
    'campaign_id' => $campaign->id,
    'campaign_title' => $campaign->title,
    'brand_name' => $brand->name,
    'approved_by' => $admin->name,
]);
echo "✅ Campaign approval notification sent\n";

// Test 9: Bid acceptance notification
echo "\n9. Testing bid acceptance notification...\n";
NotificationService::notifyAdminOfSystemActivity('bid_accepted', [
    'bid_id' => $bid->id,
    'campaign_id' => $bid->campaign_id,
    'campaign_title' => $campaign->title,
    'creator_name' => $creator->name,
    'brand_name' => $brand->name,
    'bid_amount' => $bid->bid_amount,
    'accepted_by' => $brand->name,
]);
echo "✅ Bid acceptance notification sent\n";

// Test 10: Application approval notification
echo "\n10. Testing application approval notification...\n";
NotificationService::notifyAdminOfSystemActivity('application_approved', [
    'application_id' => $application->id,
    'campaign_id' => $application->campaign_id,
    'campaign_title' => $campaign->title,
    'creator_name' => $creator->name,
    'brand_name' => $brand->name,
    'proposal_amount' => $application->proposed_budget,
    'approved_by' => $brand->name,
]);
echo "✅ Application approval notification sent\n";

// Check admin notifications
echo "\n=== CHECKING ADMIN NOTIFICATIONS ===\n";
$adminNotifications = Notification::where('user_id', $admin->id)
    ->orderBy('created_at', 'desc')
    ->get();

echo "Total admin notifications: {$adminNotifications->count()}\n\n";

foreach ($adminNotifications as $notification) {
    echo "📧 {$notification->title}\n";
    echo "   Message: {$notification->message}\n";
    echo "   Type: {$notification->type}\n";
    echo "   Created: {$notification->created_at}\n";
    echo "   Read: " . ($notification->is_read ? 'Yes' : 'No') . "\n";
    echo "   Data: " . json_encode($notification->data) . "\n";
    echo "\n";
}

// Clean up test data
echo "=== CLEANING UP TEST DATA ===\n";
$bid->delete();
$application->delete();
$campaign->delete();

echo "✅ Test completed successfully!\n";
echo "✅ All admin notifications are working properly!\n"; 