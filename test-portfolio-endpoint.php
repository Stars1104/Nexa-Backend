<?php

// Simple test to verify portfolio functionality
require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Portfolio;
use App\Models\PortfolioItem;

// Test portfolio creation and update
try {
    // Find a creator user
    $user = User::where('role', 'creator')->first();
    
    if (!$user) {
        echo "No creator user found. Creating one...\n";
        $user = User::create([
            'name' => 'Test Creator',
            'email' => 'test@creator.com',
            'password' => bcrypt('password'),
            'role' => 'creator'
        ]);
    }
    
    echo "Testing with user: {$user->name} (ID: {$user->id})\n";
    
    // Test portfolio creation
    $portfolio = $user->portfolio()->firstOrCreate([
        'title' => 'Test Portfolio',
        'bio' => 'This is a test portfolio bio',
        'profile_picture' => null
    ]);
    
    echo "Portfolio created/updated: ID {$portfolio->id}\n";
    echo "Title: {$portfolio->title}\n";
    echo "Bio: {$portfolio->bio}\n";
    echo "Profile picture URL: {$portfolio->profile_picture_url}\n";
    
    // Test portfolio item creation
    $item = $portfolio->items()->create([
        'file_path' => 'test/path/image.jpg',
        'file_name' => 'test-image.jpg',
        'file_type' => 'image/jpeg',
        'media_type' => 'image',
        'file_size' => 1024,
        'title' => 'Test Image',
        'description' => 'This is a test image',
        'order' => 1
    ]);
    
    echo "Portfolio item created: ID {$item->id}\n";
    echo "Media type: {$item->media_type}\n";
    echo "File URL: {$item->file_url}\n";
    
    // Test portfolio methods
    echo "Items count: {$portfolio->getItemsCount()}\n";
    echo "Images count: {$portfolio->getImagesCount()}\n";
    echo "Videos count: {$portfolio->getVideosCount()}\n";
    echo "Is complete: " . ($portfolio->isComplete() ? 'Yes' : 'No') . "\n";
    
    echo "\n✅ Portfolio test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 