<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SubscriptionPlan;

echo "🔍 Checking Subscription Plans in Database:\n\n";

try {
    $plans = SubscriptionPlan::all();
    
    if ($plans->isEmpty()) {
        echo "❌ No subscription plans found in database!\n";
        echo "This is likely the cause of the 400 error.\n\n";
        
        echo "Creating sample subscription plans...\n";
        
        // Create sample plans
        $samplePlans = [
            [
                'name' => 'Plano Mensal',
                'description' => 'Acesso premium por 1 mês',
                'price' => 29.90,
                'duration_months' => 1,
                'monthly_price' => 29.90,
                'savings_percentage' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Plano Trimestral',
                'description' => 'Acesso premium por 3 meses',
                'price' => 79.90,
                'duration_months' => 3,
                'monthly_price' => 26.63,
                'savings_percentage' => 11,
                'is_active' => true,
            ],
            [
                'name' => 'Plano Anual',
                'description' => 'Acesso premium por 12 meses',
                'price' => 299.90,
                'duration_months' => 12,
                'monthly_price' => 24.99,
                'savings_percentage' => 16,
                'is_active' => true,
            ]
        ];
        
        foreach ($samplePlans as $planData) {
            $plan = SubscriptionPlan::create($planData);
            echo "✅ Created plan: {$plan->name} (ID: {$plan->id})\n";
        }
        
        echo "\n✅ Sample subscription plans created successfully!\n";
        
    } else {
        echo "✅ Found " . $plans->count() . " subscription plans:\n\n";
        
        foreach ($plans as $plan) {
            echo "ID: {$plan->id}\n";
            echo "Name: {$plan->name}\n";
            echo "Price: R$ " . number_format($plan->price, 2, ',', '.') . "\n";
            echo "Duration: {$plan->duration_months} months\n";
            echo "Active: " . ($plan->is_active ? 'Yes' : 'No') . "\n";
            echo "---\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error checking subscription plans: " . $e->getMessage() . "\n";
    echo "This might be the cause of the 400 error.\n";
}

echo "\n🔧 Debug Information:\n";
echo "Simulation Mode: " . (env('PAGARME_SIMULATION_MODE') ? 'ENABLED' : 'DISABLED') . "\n";
echo "Environment: " . env('APP_ENV', 'unknown') . "\n";
echo "Database: " . env('DB_DATABASE', 'unknown') . "\n";
