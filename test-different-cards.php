<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Pagar.me configuration
$pagarMeApiKey = 'sk_ca1c6ab72ce84f14853654e13dbbe25a';
$pagarMeBaseUrl = 'https://api.pagar.me/core/v5';

echo "Testing Different Card Numbers with Pagar.me\n";
echo "============================================\n\n";

// Test card numbers to try
$testCards = [
    '4111111111111111' => 'Visa Standard',
    '4242424242424242' => 'Visa Alternative',
    '4000000000000002' => 'Visa Declined',
    '4000000000009995' => 'Visa Insufficient Funds',
    '4000000000009987' => 'Visa Lost Card',
    '4000000000009979' => 'Visa Stolen Card',
    '4000000000000069' => 'Visa Expired Card',
    '4000000000000127' => 'Visa Incorrect CVC',
    '4000000000000119' => 'Visa Processing Error',
    '5555555555554444' => 'Mastercard Standard',
    '5105105105105100' => 'Mastercard Alternative',
    '378282246310005' => 'American Express',
    '371449635398431' => 'American Express Alternative',
    '4000000000000010' => 'Visa Generic',
    '4000000000000028' => 'Visa Generic 2',
    '4000000000000036' => 'Visa Generic 3',
    '4000000000000044' => 'Visa Generic 4',
    '4000000000000051' => 'Visa Generic 5',
    '4000000000000069' => 'Visa Generic 6',
    '4000000000000077' => 'Visa Generic 7',
    '4000000000000085' => 'Visa Generic 8',
    '4000000000000093' => 'Visa Generic 9',
    '4000000000000101' => 'Visa Generic 10',
];

// First, create a test customer
echo "1. Creating test customer...\n";
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
        echo "   Customer ID: " . $customer['id'] . "\n\n";
        
        // Test each card number
        echo "2. Testing different card numbers...\n";
        echo "=====================================\n";
        
        $successfulCards = [];
        
        foreach ($testCards as $cardNumber => $description) {
            echo "Testing: {$description} ({$cardNumber})... ";
            
            $cardData = [
                'type' => 'credit',
                'number' => $cardNumber,
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
                echo "✅ SUCCESS\n";
                echo "   Card ID: " . $card['id'] . "\n";
                echo "   Last 4: " . $card['last_four_digits'] . "\n";
                echo "   Brand: " . $card['brand'] . "\n";
                
                $successfulCards[] = [
                    'number' => $cardNumber,
                    'description' => $description,
                    'card_id' => $card['id'],
                    'last4' => $card['last_four_digits'],
                    'brand' => $card['brand']
                ];
                
                // Delete the card to clean up
                Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($pagarMeApiKey . ':'),
                    'Content-Type' => 'application/json',
                ])->delete("{$pagarMeBaseUrl}/customers/{$customer['id']}/cards/{$card['id']}");
                
            } else {
                $error = $cardResponse->json();
                echo "❌ FAILED\n";
                echo "   Status: " . $cardResponse->status() . "\n";
                echo "   Error: " . ($error['message'] ?? 'Unknown error') . "\n";
            }
            
            echo "\n";
            
            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds
        }
        
        // Summary
        echo "3. Test Summary\n";
        echo "===============\n";
        if (empty($successfulCards)) {
            echo "❌ No test cards worked with this Pagar.me environment.\n";
            echo "   Recommendation: Use real cards for testing or contact Pagar.me support.\n";
        } else {
            echo "✅ Found " . count($successfulCards) . " working test cards:\n";
            foreach ($successfulCards as $card) {
                echo "   • {$card['description']}: {$card['number']} (Last 4: {$card['last4']})\n";
            }
        }
        
    } else {
        echo "❌ Customer creation failed\n";
        echo "   Status: " . $response->status() . "\n";
        echo "   Response: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n"; 