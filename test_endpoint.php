<?php

/**
 * Test Payment Endpoint
 * 
 * This script tests the payment subscription endpoint
 * Run with: php test_endpoint.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\PaymentSimulator;

echo "=== Payment Endpoint Test ===\n\n";

// Test 1: Check simulation mode
echo "1. Simulation Mode Status:\n";
$isSimulationMode = PaymentSimulator::isSimulationMode();
echo "   Simulation mode: " . ($isSimulationMode ? 'ENABLED' : 'DISABLED') . "\n";

// Test 2: Check environment
echo "\n2. Environment Configuration:\n";
echo "   PAGARME_SIMULATION_MODE: " . (env('PAGARME_SIMULATION_MODE') ?: 'not set') . "\n";
echo "   APP_ENV: " . (env('APP_ENV', 'not set')) . "\n";
echo "   PAGARME_ENVIRONMENT: " . (env('PAGARME_ENVIRONMENT', 'not set')) . "\n";

// Test 3: Check if subscription plans exist
echo "\n3. Subscription Plans Check:\n";
try {
    $plans = \App\Models\SubscriptionPlan::all();
    echo "   Total plans found: " . $plans->count() . "\n";
    foreach ($plans as $plan) {
        echo "   - Plan ID: {$plan->id}, Name: {$plan->name}, Price: R$ {$plan->price}\n";
    }
} catch (Exception $e) {
    echo "   Error loading plans: " . $e->getMessage() . "\n";
}

// Test 4: Check if users exist
echo "\n4. Users Check:\n";
try {
    $users = \App\Models\User::where('role', 'creator')->limit(5)->get();
    echo "   Total creators found: " . $users->count() . "\n";
    foreach ($users as $user) {
        echo "   - User ID: {$user->id}, Email: {$user->email}, Role: {$user->role}\n";
    }
} catch (Exception $e) {
    echo "   Error loading users: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";

if ($isSimulationMode) {
    echo "✅ Ready for simulation testing\n";
    echo "   Make sure to:\n";
    echo "   1. Login as a creator user\n";
    echo "   2. Select a subscription plan\n";
    echo "   3. Fill out the payment form\n";
    echo "   4. Check browser console for debug logs\n";
} else {
    echo "❌ Simulation mode is disabled\n";
    echo "   To enable: Set PAGARME_SIMULATION_MODE=true in .env\n";
}
