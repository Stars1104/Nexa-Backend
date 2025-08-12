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
        Schema::table('steps', function (Blueprint $table) {
            if (!Schema::hasColumn('steps', 'video_mime')) {
                $table->string('video_mime')->nullable()->after('video_path');
            }
            if (!Schema::hasColumn('steps', 'order')) {
                $table->integer('order')->default(0)->after('video_mime');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            if (Schema::hasColumn('steps', 'video_mime')) {
                $table->dropColumn('video_mime');
            }
            if (Schema::hasColumn('steps', 'order')) {
                $table->dropColumn('order');
            }
        });
    }
};
