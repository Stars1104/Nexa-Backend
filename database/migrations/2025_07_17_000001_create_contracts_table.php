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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('creator_id');
            $table->string('title');
            $table->text('description');
            $table->decimal('budget', 10, 2); // Amount in reais
            $table->integer('estimated_days');
            $table->json('requirements')->nullable();
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled', 'disputed', 'payment_failed'])->default('active');
            $table->timestamp('started_at');
            $table->timestamp('expected_completion_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('platform_fee', 10, 2); // 10% of budget
            $table->decimal('creator_amount', 10, 2); // 90% of budget
            $table->timestamps();

            $table->foreign('offer_id')->references('id')->on('offers')->onDelete('cascade');
            $table->foreign('brand_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['brand_id', 'creator_id']);
            $table->index(['status', 'expected_completion_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
}; 