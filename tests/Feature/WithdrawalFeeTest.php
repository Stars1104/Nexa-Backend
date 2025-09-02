<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Models\User;
use App\Models\CreatorBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WithdrawalFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawal_platform_fee_is_set_correctly()
    {
        // Create a withdrawal method with 10% fee
        $withdrawalMethod = WithdrawalMethod::create([
            'code' => 'test_method',
            'name' => 'Test Method',
            'description' => 'Test withdrawal method',
            'min_amount' => 10.00,
            'max_amount' => 1000.00,
            'processing_time' => '1-2 days',
            'fee' => 10.00, // 10%
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Create a user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'creator',
        ]);

        // Create creator balance
        CreatorBalance::create([
            'creator_id' => $user->id,
            'available_balance' => 1000.00,
            'pending_balance' => 0,
            'total_earned' => 1000.00,
            'total_withdrawn' => 0,
        ]);

        // Create a withdrawal
        $withdrawal = Withdrawal::create([
            'creator_id' => $user->id,
            'amount' => 100.00,
            'platform_fee' => 5.00,
            'fixed_fee' => 5.00,
            'withdrawal_method' => 'test_method',
            'withdrawal_details' => [],
            'status' => 'pending',
        ]);

        // Assert the platform fee is set correctly
        $this->assertEquals(5.00, $withdrawal->platform_fee); // 5%
        $this->assertEquals(5.00, $withdrawal->fixed_fee); // R$5
        
        // Assert the percentage fee calculation is correct
        $this->assertEquals(10.00, $withdrawal->percentage_fee); // 10%
        $this->assertEquals(10.00, $withdrawal->percentage_fee_amount); // 100 * 10% = 10
        
        // Assert the platform fee calculation is correct
        $this->assertEquals(5.00, $withdrawal->platform_fee_amount); // 100 * 5% = 5
        
        // Assert the total fees calculation is correct (including fixed fee)
        $this->assertEquals(20.00, $withdrawal->total_fees); // 10 + 5 + 5 = 20
        
        // Assert the net amount calculation is correct
        $this->assertEquals(80.00, $withdrawal->net_amount); // 100 - 20 = 80
    }

    public function test_withdrawal_formatted_fees_are_correct()
    {
        // Create a withdrawal method with 5% fee
        $withdrawalMethod = WithdrawalMethod::create([
            'code' => 'test_method_2',
            'name' => 'Test Method 2',
            'description' => 'Test withdrawal method with 5% fee',
            'min_amount' => 10.00,
            'max_amount' => 1000.00,
            'processing_time' => '1-2 days',
            'fee' => 5.00, // 5%
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Create a user
        $user = User::create([
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
            'role' => 'creator',
        ]);

        // Create creator balance
        CreatorBalance::create([
            'creator_id' => $user->id,
            'available_balance' => 1000.00,
            'pending_balance' => 0,
            'total_earned' => 1000.00,
            'total_withdrawn' => 0,
        ]);

        // Create a withdrawal
        $withdrawal = Withdrawal::create([
            'creator_id' => $user->id,
            'amount' => 200.00,
            'platform_fee' => 5.00,
            'fixed_fee' => 5.00,
            'withdrawal_method' => 'test_method_2',
            'withdrawal_details' => [],
            'status' => 'pending',
        ]);

        // Assert formatted fees are correct
        $this->assertEquals('5.00%', $withdrawal->formatted_platform_fee);
        $this->assertEquals('R$ 10,00', $withdrawal->formatted_platform_fee_amount);
        $this->assertEquals('R$ 5,00', $withdrawal->formatted_fixed_fee);
        $this->assertEquals('R$ 10,00', $withdrawal->formatted_percentage_fee_amount);
        $this->assertEquals('R$ 25,00', $withdrawal->formatted_total_fees); // 10 + 10 + 5 = 25
        $this->assertEquals('R$ 175,00', $withdrawal->formatted_net_amount); // 200 - 25 = 175
    }
}
