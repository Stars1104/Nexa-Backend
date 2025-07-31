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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('reviewer_id'); // Brand who gives the review
            $table->unsignedBigInteger('reviewed_id'); // Creator who receives the review
            $table->integer('rating'); // 1-5 stars
            $table->text('comment')->nullable();
            $table->json('rating_categories')->nullable(); // JSON with specific ratings (communication, quality, etc.)
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->unique(['contract_id', 'reviewer_id']); // One review per contract per reviewer
            $table->index(['reviewed_id', 'rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
}; 