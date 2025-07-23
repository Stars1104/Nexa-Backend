<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Pagar.me configuration
$pagarMeApiKey = 'sk_ca1c6ab72ce84f14853654e13dbbe25a';
$pagarMeBaseUrl = 'https://api.pagar.me/core/v5';

echo "Testing Pagar.me Integration\n";
echo "============================\n\n";

// Test 1: Check API connectivity
echo "1. Testing API connectivity...\n";
try {
    $response = Http::withHeaders([
        'Authorization' => 'Basic ' . base64_encode($pagarMeApiKey . ':'),
        'Content-Type' => 'application/json',
    ])->get("{$pagarMeBaseUrl}/customers");

    if ($response->successful()) {
        echo "✅ API connectivity successful\n";
        echo "   Status: " . $response->status() . "\n";
    } else {
        echo "❌ API connectivity failed\n";
        echo "   Status: " . $response->status() . "\n";
        echo "   Response: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "❌ API connectivity error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Create a test customer
echo "2. Testing customer creation...\n";
try {
    $customerData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'type' => 'individual',
        'document' => '12345678909',
        'phones' => [
            'mobile_phone' => [
                'country_code' => '55',
                'area_code' => '11',
                'number' => '999999999'
            ]
        ]
    ];

    $response = Http::withHeaders([
        'Authorization' => 'Basic ' . base64_encode($pagarMeApiKey . ':'),
        'Content-Type' => 'application/json',
    ])->post("{$pagarMeBaseUrl}/customers", $customerData);

    if ($response->successful()) {
        $customer = $response->json();
        echo "✅ Customer created successfully\n";
        echo "   Customer ID: " . $customer['id'] . "\n";
        echo "   Name: " . $customer['name'] . "\n";
        
        // Test 3: Create a test card
        echo "\n3. Testing card creation...\n";
        
        $cardData = [
            'type' => 'credit',
            'number' => '4111111111111111',
            'holder_name' => 'Test User',
            'exp_month' => 12,
            'exp_year' => 2025,
            'cvv' => '123',
            'billing_address' => [
                'line_1' => 'Rua Teste',
                'line_2' => 'Apto 123',
                'zip_code' => '01234-567',
                'city' => 'São Paulo',
                'state' => 'SP',
                'country' => 'BR'
            ]
        ];

        $cardResponse = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($pagarMeApiKey . ':'),
            'Content-Type' => 'application/json',
        ])->post("{$pagarMeBaseUrl}/customers/{$customer['id']}/cards", $cardData);

        if ($cardResponse->successful()) {
            $card = $cardResponse->json();
            echo "✅ Card created successfully\n";
            echo "   Card ID: " . $card['id'] . "\n";
            echo "   Last 4 digits: " . $card['last_four_digits'] . "\n";
            echo "   Brand: " . $card['brand'] . "\n";
            
            // Test 4: Process a test payment
            echo "\n4. Testing payment processing...\n";
            
            $paymentData = [
                'amount' => 1000, // R$ 10,00 in cents
                'payment' => [
                    'payment_method' => 'credit_card',
                    'credit_card' => [
                        'operation_type' => 'auth_and_capture',
                        'installments' => 1,
                        'statement_descriptor' => 'NEXA',
                        'card_id' => $card['id']
                    ]
                ],
                'items' => [
                    [
                        'amount' => 1000,
                        'description' => 'Test Payment',
                        'quantity' => 1,
                        'code' => 'TEST_001'
                    ]
                ],
                'customer' => [
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'email' => $customer['email'],
                    'type' => 'individual',
                    'document' => '12345678909'
                ],
                'code' => 'TEST_PAYMENT_' . time(),
                'metadata' => [
                    'test' => true
                ]
            ];

            $paymentResponse = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($pagarMeApiKey . ':'),
                'Content-Type' => 'application/json',
            ])->post("{$pagarMeBaseUrl}/orders", $paymentData);

            if ($paymentResponse->successful()) {
                $payment = $paymentResponse->json();
                echo "✅ Payment processed successfully\n";
                echo "   Order ID: " . $payment['id'] . "\n";
                echo "   Status: " . $payment['status'] . "\n";
                echo "   Amount: R$ " . number_format($payment['amount'] / 100, 2, ',', '.') . "\n";
            } else {
                echo "❌ Payment processing failed\n";
                echo "   Status: " . $paymentResponse->status() . "\n";
                echo "   Response: " . $paymentResponse->body() . "\n";
            }
            
        } else {
            echo "❌ Card creation failed\n";
            echo "   Status: " . $cardResponse->status() . "\n";
            echo "   Response: " . $cardResponse->body() . "\n";
        }
        
    } else {
        echo "❌ Customer creation failed\n";
        echo "   Status: " . $response->status() . "\n";
        echo "   Response: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "❌ Customer creation error: " . $e->getMessage() . "\n";
}

echo "\n";
echo "Test completed!\n"; 