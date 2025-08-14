<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Added missing import for DB facade

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 8, 2);
            $table->integer('duration_months');
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Insert the three subscription plans
        DB::table('subscription_plans')->insert([
            [
                'name' => 'Monthly Plan',
                'description' => '1-month subscription to Nexa Premium',
                'price' => 29.99,
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
                'name' => '6-Month Plan',
                'description' => '6-month subscription to Nexa Premium',
                'price' => 119.94, // 19.99 * 6 months
                'duration_months' => 6,
                'is_active' => true,
                'features' => json_encode([
                    'Aplicações ilimitadas em campanhas',
                    'Acesso a todas as campanhas exclusivas',
                    'Prioridade na aprovação de campanhas',
                    'Suporte premium via chat',
                    'Ferramentas avançadas de criação de conteúdo',
                    'Economia de 33% comparado ao plano mensal'
                ]),
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '6-Year Plan',
                'description' => '6-year subscription to Nexa Premium',
                'price' => 1799.28, // 24.99 * 12 months * 6 years
                'duration_months' => 72,
                'is_active' => true,
                'features' => json_encode([
                    'Aplicações ilimitadas em campanhas',
                    'Acesso a todas as campanhas exclusivas',
                    'Prioridade na aprovação de campanhas',
                    'Suporte premium via chat',
                    'Ferramentas avançadas de criação de conteúdo',
                    'Economia de 17% comparado ao plano mensal',
                    'Acesso garantido por 6 anos'
                ]),
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
}; 