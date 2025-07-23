<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Portfolio;
use App\Models\PortfolioItem;

// Test portfolio API functionality
try {
    echo "Testing Portfolio API...\n\n";
    
    // Find a creator user
    $user = User::where('role', 'creator')->first();
    
    if (!$user) {
        echo "No creator user found.\n";
        exit(1);
    }
    
    echo "Testing with user: {$user->name} (ID: {$user->id})\n\n";
    
    // Test portfolio retrieval
    $portfolio = $user->portfolio()->with('items')->first();
    
    if (!$portfolio) {
        echo "No portfolio found for user.\n";
        exit(1);
    }
    
    echo "Portfolio found: ID {$portfolio->id}\n";
    echo "Title: {$portfolio->title}\n";
    echo "Bio: {$portfolio->bio}\n";
    echo "Profile picture URL: {$portfolio->profile_picture_url}\n";
    echo "Items count: {$portfolio->items->count()}\n\n";
    
    // Test portfolio items
    foreach ($portfolio->items as $item) {
        echo "Item ID: {$item->id}\n";
        echo "  File: {$item->file_path}\n";
        echo "  Media Type: {$item->media_type}\n";
        echo "  File URL: {$item->file_url}\n";
        echo "  File accessible: " . (file_exists(storage_path('app/public/' . $item->file_path)) ? 'Yes' : 'No') . "\n";
        echo "\n";
    }
    
    // Test portfolio methods
    echo "Portfolio Statistics:\n";
    echo "  Items count: {$portfolio->getItemsCount()}\n";
    echo "  Images count: {$portfolio->getImagesCount()}\n";
    echo "  Videos count: {$portfolio->getVideosCount()}\n";
    echo "  Is complete: " . ($portfolio->isComplete() ? 'Yes' : 'No') . "\n";
    
    echo "\n✅ Portfolio API test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 