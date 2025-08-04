<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For PostgreSQL, we need to drop and recreate the column
        Schema::table('contracts', function (Blueprint $table) {
            // Drop the existing enum column
            $table->dropColumn('status');
        });

        Schema::table('contracts', function (Blueprint $table) {
            // Recreate with new enum values
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled', 'disputed'])->default('active')->after('requirements');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Drop the column
            $table->dropColumn('status');
        });

        Schema::table('contracts', function (Blueprint $table) {
            // Recreate with original enum values
            $table->enum('status', ['active', 'completed', 'cancelled', 'disputed'])->default('active')->after('requirements');
        });
    }
};
