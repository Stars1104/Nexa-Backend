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
        Schema::create('direct_chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('creator_id');
            $table->string('room_id')->unique(); // Unique room identifier
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedBigInteger('connection_request_id')->nullable(); // Optional connection request
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('connection_request_id')->references('id')->on('connection_requests')->onDelete('set null');
            
            // Ensure unique combination of brand and creator
            $table->unique(['brand_id', 'creator_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direct_chat_rooms');
    }
}; 