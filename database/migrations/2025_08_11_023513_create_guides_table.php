<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('audience')->nullable();
            $table->text('description')->nullable();
            $table->string('video_path')->nullable(); // stored path in storage disk
            $table->string('video_mime')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // optional
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guides');
    }
};