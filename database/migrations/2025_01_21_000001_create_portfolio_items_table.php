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
        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->onDelete('cascade');
            $table->string('file_path'); // File storage path
            $table->string('file_name'); // Original file name
            $table->string('file_type'); // MIME type
            $table->string('media_type'); // 'image' or 'video'
            $table->bigInteger('file_size'); // File size in bytes
            $table->string('title')->nullable(); // Optional title for the item
            $table->text('description')->nullable(); // Optional description
            $table->integer('order')->default(0); // Display order
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['portfolio_id', 'order']);
            $table->index('media_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_items');
    }
}; 