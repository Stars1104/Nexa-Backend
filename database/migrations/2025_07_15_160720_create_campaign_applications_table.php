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
        Schema::create('campaign_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('proposal')->nullable(); // Creator's proposal/cover letter
            $table->json('portfolio_links')->nullable(); // Array of portfolio links
            $table->integer('estimated_delivery_days')->nullable();
            $table->decimal('proposed_budget', 10, 2)->nullable(); // Creator's proposed budget
            $table->text('rejection_reason')->nullable(); // Reason if rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null'); // Brand who reviewed
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            // Ensure one application per creator per campaign
            $table->unique(['campaign_id', 'creator_id']);
            
            // Indexes for better performance
            $table->index(['campaign_id', 'status']);
            $table->index(['creator_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_applications');
    }
};
