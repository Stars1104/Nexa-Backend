<?php

/**
 * Payment Simulation Test Script
 * 
 * This script tests the payment simulation functionality
 * Run with: php test_simulation.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\PaymentSimulator;

echo "=== Payment Simulation Test ===\n\n";

// Test 1: Check simulation mode
echo "1. Testing simulation mode detection:\n";
$isSimulationMode = PaymentSimulator::isSimulationMode();
echo "   Simulation mode enabled: " . ($isSimulationMode ? 'YES' : 'NO') . "\n\n";

// Test 2: Test subscription payment simulation
echo "2. Testing subscription payment simulation:\n";
$mockUser = (object) [
    'id' => 1,
    'name' => 'Test User',
    'email' => 'test@example.com',
    'isCreator' => function() { return true; }
];

$mockPlan = (object) [
    'id' => 1,
    'name' => 'Premium Plan',
    'monthly_price' => 29.90,
    'duration_months' => 1
];

$mockRequest = [
    'card_number' => '4111111111111111',
    'card_holder_name' => 'Test User',
    'card_expiration_date' => '1225',
    'card_cvv' => '123',
    'cpf' => '111.444.777-35'
];

try {
    $result = PaymentSimulator::simulateSubscriptionPayment($mockRequest, $mockUser, $mockPlan);
    echo "   Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "   Transaction ID: " . $result['transaction_id'] . "\n";
    echo "   Amount: R$ " . number_format($result['amount'], 2, ',', '.') . "\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Test account payment simulation
echo "3. Testing account payment simulation:\n";
$mockAccountRequest = [
    'amount' => 5000, // R$ 50.00 in cents
    'description' => 'Test Account Payment',
    'account_id' => 'test_account_123',
    'email' => 'test@example.com'
];

try {
    $result = PaymentSimulator::simulateAccountPayment($mockAccountRequest, $mockUser);
    echo "   Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "   Transaction ID: " . $result['transaction_id'] . "\n";
    echo "   Amount: R$ " . number_format($result['amount'] / 100, 2, ',', '.') . "\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Test contract payment simulation
echo "4. Testing contract payment simulation:\n";
$mockContractRequest = [
    'amount' => 10000, // R$ 100.00 in cents
    'contract_id' => 123,
    'description' => 'Test Contract Payment'
];

try {
    $result = PaymentSimulator::simulateContractPayment($mockContractRequest, $mockUser);
    echo "   Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "   Transaction ID: " . $result['transaction_id'] . "\n";
    echo "   Amount: R$ " . number_format($result['amount'] / 100, 2, ',', '.') . "\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Test withdrawal simulation
echo "5. Testing withdrawal simulation:\n";
try {
    $result = PaymentSimulator::simulateWithdrawalProcessing(123, 'pix');
    echo "   Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "   Transaction ID: " . $result['transaction_id'] . "\n";
    echo "   Method: " . $result['method'] . "\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 6: Test payment method creation simulation
echo "6. Testing payment method creation simulation:\n";
$mockCardRequest = [
    'card_number' => '4111111111111111',
    'holder_name' => 'Test User',
    'exp_month' => 12,
    'exp_year' => 2025,
    'cvv' => '123'
];

try {
    $result = PaymentSimulator::simulatePaymentMethodCreation($mockCardRequest, $mockUser);
    echo "   Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "   Card ID: " . $result['card_id'] . "\n";
    echo "   Brand: " . $result['brand'] . "\n";
    echo "   Last 4: " . $result['last4'] . "\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 7: Test error simulation
echo "7. Testing error simulation:\n";
$shouldError = PaymentSimulator::shouldSimulateError();
echo "   Should simulate error: " . ($shouldError ? 'YES' : 'NO') . "\n";

if ($shouldError) {
    $errorResult = PaymentSimulator::simulateError('Test error simulation');
    echo "   Error result: " . ($errorResult['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "   Error message: " . $errorResult['message'] . "\n";
}

echo "\n=== Test Complete ===\n";
echo "Note: This test only validates the simulation logic.\n";
echo "To test with real database, run the Laravel application with PAGARME_SIMULATION_MODE=true\n";
