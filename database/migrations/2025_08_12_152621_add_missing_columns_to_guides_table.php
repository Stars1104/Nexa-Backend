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
        Schema::table('guides', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('description');
            $table->string('video_mime')->nullable()->after('video_path');
            $table->unsignedBigInteger('created_by')->nullable()->after('video_mime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn(['video_path', 'video_mime', 'created_by']);
        });
    }
};
