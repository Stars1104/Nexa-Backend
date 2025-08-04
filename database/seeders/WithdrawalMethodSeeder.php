<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WithdrawalMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'code' => 'pagarme_bank_transfer',
                'name' => 'Transferência Bancária via Pagar.me',
                'description' => 'Transferência para conta bancária registrada via Pagar.me',
                'min_amount' => 10.00,
                'max_amount' => 10000.00,
                'processing_time' => '1-2 dias úteis',
                'fee' => 0.00,
                'is_active' => true,
                'required_fields' => json_encode([]), // No additional fields needed since bank account is already registered
                'field_config' => json_encode([]), // No additional configuration needed
                'sort_order' => 1,
            ],
        ];

        foreach ($methods as $method) {
            DB::table('withdrawal_methods')->insert($method);
        }
    }
}
