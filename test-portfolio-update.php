<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\Portfolio;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PORTFOLIO UPDATE TEST ===\n";

// Get user
$user = User::find(7);
echo "User ID: " . $user->id . "\n";
echo "User Role: " . $user->role . "\n";
echo "Is Creator: " . ($user->isCreator() ? 'YES' : 'NO') . "\n";

// Get portfolio
$portfolio = $user->portfolio()->firstOrCreate();
echo "Portfolio ID: " . $portfolio->id . "\n";
echo "Current Title: " . ($portfolio->title ?? 'NULL') . "\n";
echo "Current Bio: " . ($portfolio->bio ?? 'NULL') . "\n";

// Test update
$testTitle = "Test Title " . time();
$testBio = "Test Bio " . time();

echo "\nUpdating with:\n";
echo "Title: " . $testTitle . "\n";
echo "Bio: " . $testBio . "\n";

$portfolio->update([
    'title' => $testTitle,
    'bio' => $testBio
]);

// Refresh and check
$portfolio->refresh();
echo "\nAfter update:\n";
echo "Title: " . ($portfolio->title ?? 'NULL') . "\n";
echo "Bio: " . ($portfolio->bio ?? 'NULL') . "\n";

echo "\nTest completed!\n"; 