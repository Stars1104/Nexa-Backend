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
        Schema::create('brand_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('users')->onDelete('cascade');
            $table->string('pagarme_customer_id');
            $table->string('pagarme_card_id');
            $table->string('card_brand')->nullable();
            $table->string('card_last4')->nullable();
            $table->string('card_holder_name');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['brand_id', 'pagarme_card_id']);
            $table->index(['brand_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_payment_methods');
    }
}; 