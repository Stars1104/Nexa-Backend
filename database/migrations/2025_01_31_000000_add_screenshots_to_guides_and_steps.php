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
            $table->json('screenshots')->nullable()->after('video_mime'); // Array of screenshot paths
        });

        Schema::table('steps', function (Blueprint $table) {
            $table->json('screenshots')->nullable()->after('video_mime'); // Array of screenshot paths
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn('screenshots');
        });

        Schema::table('steps', function (Blueprint $table) {
            $table->dropColumn('screenshots');
        });
    }
}; 