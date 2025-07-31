<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('pagarme_transaction_id')->unique();
            $table->string('status'); // paid, pending, failed, canceled
            $table->decimal('amount', 10, 2); // Amount in cents (4999 = R$ 49.99)
            $table->string('payment_method'); // credit_card, boleto, pix
            $table->string('card_brand')->nullable(); // visa, mastercard, etc.
            $table->string('card_last4')->nullable();
            $table->string('card_holder_name')->nullable();
            $table->json('payment_data')->nullable(); // Store additional payment info
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // When premium expires
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('pagarme_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
