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
        Schema::create('connection_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id'); // User sending the request
            $table->unsignedBigInteger('receiver_id'); // User receiving the request
            $table->string('status')->default('pending'); // pending, accepted, rejected, cancelled
            $table->text('message')->nullable(); // Optional message with the request
            $table->unsignedBigInteger('campaign_id')->nullable(); // Optional campaign context
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->onDelete('cascade');
            
            // Ensure unique connection request between two users
            $table->unique(['sender_id', 'receiver_id']);
            
            // Indexes for better performance
            $table->index(['receiver_id', 'status']);
            $table->index(['sender_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connection_requests');
    }
}; 