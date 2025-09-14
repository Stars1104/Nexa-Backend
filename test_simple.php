<?php

/**
 * Simple Test for Payment Simulation
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\PaymentSimulator;

echo "=== Simple Payment Simulation Test ===\n\n";

// Test 1: Check if simulation mode is enabled
$isSimulationMode = PaymentSimulator::isSimulationMode();
echo "Simulation Mode: " . ($isSimulationMode ? 'ENABLED' : 'DISABLED') . "\n";

// Test 2: Test the simulation function directly
if ($isSimulationMode) {
    echo "\nTesting simulation function...\n";
    
    $mockUser = (object) [
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'isCreator' => function() { return true; }
    ];
    
    $mockPlan = (object) [
        'id' => 1,
        'name' => 'Premium Plan',
        'monthly_price' => 39.90,
        'duration_months' => 1
    ];
    
    $mockRequest = [
        'card_number' => '4111111111111111',
        'card_holder_name' => 'Oleh Snihur',
        'card_expiration_date' => '1230',
        'card_cvv' => '123',
        'cpf' => '111.444.777-35'
    ];
    
    try {
        $result = PaymentSimulator::simulateSubscriptionPayment($mockRequest, $mockUser, $mockPlan);
        echo "Simulation Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
        if ($result['success']) {
            echo "Transaction ID: " . $result['transaction_id'] . "\n";
        } else {
            echo "Error: " . ($result['message'] ?? 'Unknown error') . "\n";
        }
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
} else {
    echo "\nSimulation mode is disabled. Enable it first.\n";
}

echo "\n=== Test Complete ===\n";
