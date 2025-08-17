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
        Schema::create('campaign_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->onDelete('cascade');
            $table->string('milestone_type'); // script_submission, script_approval, video_submission, final_approval
            $table->string('title');
            $table->text('description')->nullable();
            $table->datetime('deadline');
            $table->datetime('completed_at')->nullable();
            $table->string('status')->default('pending'); // pending, approved, delayed, completed
            $table->text('comment')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_size')->nullable();
            $table->string('file_type')->nullable();
            $table->text('justification')->nullable(); // For delayed milestones
            $table->boolean('is_delayed')->default(false);
            $table->datetime('delay_notified_at')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'milestone_type']);
            $table->index(['status', 'is_delayed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_timelines');
    }
}; 