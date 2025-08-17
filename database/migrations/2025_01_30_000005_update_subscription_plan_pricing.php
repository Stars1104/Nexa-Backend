<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the subscription plans with new pricing
        DB::table('subscription_plans')->where('name', 'Monthly Plan')->update([
            'name' => 'Monthly Plan',
            'description' => '1-month subscription to Nexa Premium',
            'price' => 39.90,
            'duration_months' => 1,
            'features' => json_encode([
                'Aplicações ilimitadas em campanhas',
                'Acesso a todas as campanhas exclusivas',
                'Prioridade na aprovação de campanhas',
                'Suporte premium via chat',
                'Ferramentas avançadas de criação de conteúdo'
            ]),
            'updated_at' => now(),
        ]);

        DB::table('subscription_plans')->where('name', '6-Month Plan')->update([
            'name' => 'Six-Month Plan',
            'description' => '6-month subscription to Nexa Premium',
            'price' => 29.90,
            'duration_months' => 6,
            'features' => json_encode([
                'Aplicações ilimitadas em campanhas',
                'Acesso a todas as campanhas exclusivas',
                'Prioridade na aprovação de campanhas',
                'Suporte premium via chat',
                'Ferramentas avançadas de criação de conteúdo',
                'Economia significativa comparado ao plano mensal'
            ]),
            'updated_at' => now(),
        ]);

        DB::table('subscription_plans')->where('name', '6-Year Plan')->update([
            'name' => 'Annual Plan',
            'description' => '12-month subscription to Nexa Premium',
            'price' => 19.90,
            'duration_months' => 12,
            'features' => json_encode([
                'Aplicações ilimitadas em campanhas',
                'Acesso a todas as campanhas exclusivas',
                'Prioridade na aprovação de campanhas',
                'Suporte premium via chat',
                'Ferramentas avançadas de criação de conteúdo',
                'Melhor valor - economia máxima comparado ao plano mensal'
            ]),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original pricing
        DB::table('subscription_plans')->where('name', 'Monthly Plan')->update([
            'price' => 29.99,
            'updated_at' => now(),
        ]);

        DB::table('subscription_plans')->where('name', 'Six-Month Plan')->update([
            'name' => '6-Month Plan',
            'price' => 119.94,
            'duration_months' => 6,
            'features' => json_encode([
                'Aplicações ilimitadas em campanhas',
                'Acesso a todas as campanhas exclusivas',
                'Prioridade na aprovação de campanhas',
                'Suporte premium via chat',
                'Ferramentas avançadas de criação de conteúdo',
                'Economia de 33% comparado ao plano mensal'
            ]),
            'updated_at' => now(),
        ]);

        DB::table('subscription_plans')->where('name', 'Annual Plan')->update([
            'name' => '6-Year Plan',
            'price' => 1799.28,
            'duration_months' => 72,
            'features' => json_encode([
                'Aplicações ilimitadas em campanhas',
                'Acesso a todas as campanhas exclusivas',
                'Prioridade na aprovação de campanhas',
                'Suporte premium via chat',
                'Ferramentas avançadas de criação de conteúdo',
                'Economia de 17% comparado ao plano mensal',
                'Acesso garantido por 6 anos'
            ]),
            'updated_at' => now(),
        ]);
    }
}; 