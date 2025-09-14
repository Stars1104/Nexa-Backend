<?php

/**
 * Test Payment Simulation Configuration
 * 
 * This script tests if the payment simulation is properly configured
 * Run with: php test_payment_simulation.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\PaymentSimulator;

echo "=== Payment Simulation Configuration Test ===\n\n";

// Test 1: Check if simulation mode is enabled
echo "1. Testing simulation mode configuration:\n";
$isSimulationMode = PaymentSimulator::isSimulationMode();
echo "   Simulation mode enabled: " . ($isSimulationMode ? 'YES' : 'NO') . "\n";

// Test 2: Check environment variables
echo "\n2. Testing environment variables:\n";
echo "   PAGARME_SIMULATION_MODE: " . (env('PAGARME_SIMULATION_MODE') ?: 'not set') . "\n";
echo "   PAGARME_API_KEY: " . (env('PAGARME_API_KEY') ? 'set' : 'not set') . "\n";
echo "   PAGARME_ENVIRONMENT: " . (env('PAGARME_ENVIRONMENT', 'not set')) . "\n";

// Test 3: Check config values
echo "\n3. Testing config values:\n";
echo "   services.pagarme.simulation_mode: " . (config('services.pagarme.simulation_mode') ? 'true' : 'false') . "\n";
echo "   services.pagarme.api_key: " . (config('services.pagarme.api_key') ? 'set' : 'not set') . "\n";

// Test 4: Check if we can create a mock user
echo "\n4. Testing mock user creation:\n";
try {
    $mockUser = (object) [
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'isCreator' => function() { return true; }
    ];
    echo "   Mock user created: YES\n";
} catch (Exception $e) {
    echo "   Mock user creation failed: " . $e->getMessage() . "\n";
}

// Test 5: Check if we can create a mock subscription plan
echo "\n5. Testing mock subscription plan creation:\n";
try {
    $mockPlan = (object) [
        'id' => 1,
        'name' => 'Premium Plan',
        'monthly_price' => 39.90,
        'duration_months' => 1
    ];
    echo "   Mock subscription plan created: YES\n";
} catch (Exception $e) {
    echo "   Mock subscription plan creation failed: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";

if ($isSimulationMode) {
    echo "✅ Payment simulation is ENABLED - All payments will be simulated\n";
    echo "   To disable simulation, set PAGARME_SIMULATION_MODE=false in .env\n";
} else {
    echo "❌ Payment simulation is DISABLED - Real payments will be processed\n";
    echo "   To enable simulation, set PAGARME_SIMULATION_MODE=true in .env\n";
}
