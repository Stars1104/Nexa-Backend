<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestPagarMe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:pagarme';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Pagar.me API integration';

    private $pagarMeApiKey;
    private $pagarMeBaseUrl = 'https://api.pagar.me/core/v5';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->pagarMeApiKey = config('services.pagarme.secret_key');
        
        if (!$this->pagarMeApiKey) {
            $this->error('Pagar.me secret key not configured!');
            return 1;
        }

        $this->info('Testing Pagar.me Integration');
        $this->info('============================');
        $this->newLine();

        // Test 1: Check API connectivity
        $this->info('1. Testing API connectivity...');
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                'Content-Type' => 'application/json',
            ])->get("{$this->pagarMeBaseUrl}/customers");

            if ($response->successful()) {
                $this->info('✅ API connectivity successful');
                $this->line('   Status: ' . $response->status());
            } else {
                $this->error('❌ API connectivity failed');
                $this->line('   Status: ' . $response->status());
                $this->line('   Response: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('❌ API connectivity error: ' . $e->getMessage());
        }

        $this->newLine();

        // Test 2: Create a test customer
        $this->info('2. Testing customer creation...');
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
                'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                'Content-Type' => 'application/json',
            ])->post("{$this->pagarMeBaseUrl}/customers", $customerData);

            if ($response->successful()) {
                $customer = $response->json();
                $this->info('✅ Customer created successfully');
                $this->line('   Customer ID: ' . $customer['id']);
                $this->line('   Name: ' . $customer['name']);
                
                // Test 3: Create a test card
                $this->newLine();
                $this->info('3. Testing card creation...');
                
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
                    'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                    'Content-Type' => 'application/json',
                ])->post("{$this->pagarMeBaseUrl}/customers/{$customer['id']}/cards", $cardData);

                if ($cardResponse->successful()) {
                    $card = $cardResponse->json();
                    $this->info('✅ Card created successfully');
                    $this->line('   Card ID: ' . $card['id']);
                    $this->line('   Last 4 digits: ' . $card['last_four_digits']);
                    $this->line('   Brand: ' . $card['brand']);
                    
                    // Test 4: Process a test payment
                    $this->newLine();
                    $this->info('4. Testing payment processing...');
                    
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
                        'Authorization' => 'Basic ' . base64_encode($this->pagarMeApiKey . ':'),
                        'Content-Type' => 'application/json',
                    ])->post("{$this->pagarMeBaseUrl}/orders", $paymentData);

                    if ($paymentResponse->successful()) {
                        $payment = $paymentResponse->json();
                        $this->info('✅ Payment processed successfully');
                        $this->line('   Order ID: ' . $payment['id']);
                        $this->line('   Status: ' . $payment['status']);
                        $this->line('   Amount: R$ ' . number_format($payment['amount'] / 100, 2, ',', '.'));
                    } else {
                        $this->error('❌ Payment processing failed');
                        $this->line('   Status: ' . $paymentResponse->status());
                        $this->line('   Response: ' . $paymentResponse->body());
                    }
                    
                } else {
                    $this->error('❌ Card creation failed');
                    $this->line('   Status: ' . $cardResponse->status());
                    $this->line('   Response: ' . $cardResponse->body());
                }
                
            } else {
                $this->error('❌ Customer creation failed');
                $this->line('   Status: ' . $response->status());
                $this->line('   Response: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('❌ Customer creation error: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('Test completed!');
        
        return 0;
    }
} 