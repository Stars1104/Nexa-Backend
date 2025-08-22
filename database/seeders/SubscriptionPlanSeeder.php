<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing subscription plans
        DB::table('subscription_plans')->truncate();
        $plans = [
            [
                'name' => 'Plano Mensal',
                'description' => 'Assinatura de 1 mês do Nexa Premium',
                'price' => 39.90,
                'duration_months' => 1,
                'is_active' => true,
                'features' => json_encode([
                    'Aplicações ilimitadas em campanhas',
                    'Acesso a todas as campanhas exclusivas',
                    'Prioridade na aprovação de campanhas',
                    'Suporte premium via chat',
                    'Ferramentas avançadas de criação de conteúdo'
                ]),
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Plano Semestral',
                'description' => 'Assinatura de 6 meses do Nexa Premium',
                'price' => 29.90,
                'duration_months' => 6,
                'is_active' => true,
                'features' => json_encode([
                    'Aplicações ilimitadas em campanhas',
                    'Acesso a todas as campanhas exclusivas',
                    'Prioridade na aprovação de campanhas',
                    'Suporte premium via chat',
                    'Ferramentas avançadas de criação de conteúdo',
                    'Economia significativa comparado ao plano mensal'
                ]),
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Plano Anual',
                'description' => 'Assinatura de 12 meses do Nexa Premium',
                'price' => 19.90,
                'duration_months' => 12,
                'is_active' => true,
                'features' => json_encode([
                    'Aplicações ilimitadas em campanhas',
                    'Acesso a todas as campanhas exclusivas',
                    'Prioridade na aprovação de campanhas',
                    'Suporte premium via chat',
                    'Ferramentas avançadas de criação de conteúdo',
                    'Melhor valor - economia máxima comparado ao plano mensal'
                ]),
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        

        foreach ($plans as $plan) {
            DB::table('subscription_plans')->insert($plan);
        }
    }
} 