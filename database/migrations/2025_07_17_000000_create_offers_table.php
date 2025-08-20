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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('creator_id');
            $table->unsignedBigInteger('chat_room_id');
            $table->string('title');
            $table->text('description');
            $table->decimal('budget', 10, 2); // Amount in reais
            $table->integer('estimated_days'); // Estimated completion time
            $table->json('requirements')->nullable(); // JSON array of requirements
            $table->enum('status', ['pending', 'accepted', 'rejected', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('expires_at'); // 3 days from creation
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('chat_room_id')->references('id')->on('chat_rooms')->onDelete('cascade');
            
            $table->index(['brand_id', 'creator_id']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
}; 