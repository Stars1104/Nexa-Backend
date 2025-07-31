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
        Schema::create('creator_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('creator_id');
            $table->decimal('available_balance', 10, 2)->default(0); // Available for withdrawal
            $table->decimal('pending_balance', 10, 2)->default(0); // Pending payments
            $table->decimal('total_earned', 10, 2)->default(0); // Total earned (including withdrawn)
            $table->decimal('total_withdrawn', 10, 2)->default(0); // Total withdrawn
            $table->timestamps();

            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('creator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creator_balances');
    }
}; 