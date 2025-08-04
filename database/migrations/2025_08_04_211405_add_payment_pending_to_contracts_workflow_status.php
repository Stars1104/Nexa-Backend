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
        // For PostgreSQL, we need to drop and recreate the column
        Schema::table('contracts', function (Blueprint $table) {
            // Drop the existing enum column
            $table->dropColumn('workflow_status');
        });

        Schema::table('contracts', function (Blueprint $table) {
            // Recreate with new enum values including payment_pending
            $table->enum('workflow_status', [
                'active',
                'waiting_review', 
                'payment_pending',
                'payment_available',
                'payment_withdrawn',
                'terminated'
            ])->default('active')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Drop the column
            $table->dropColumn('workflow_status');
        });

        Schema::table('contracts', function (Blueprint $table) {
            // Recreate with original enum values
            $table->enum('workflow_status', [
                'active',
                'waiting_review', 
                'payment_available',
                'payment_withdrawn',
                'terminated'
            ])->default('active')->after('status');
        });
    }
};
