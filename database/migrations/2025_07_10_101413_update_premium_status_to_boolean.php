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
        Schema::table('users', function (Blueprint $table) {
            // Add the new boolean column
            $table->boolean('has_premium')->default(false)->after('language');
        });

        // Update existing data: set has_premium = true if premium_status is 'premium'
        DB::table('users')->where('premium_status', 'premium')->update(['has_premium' => true]);
        
        // Drop the old premium_status column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('premium_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add back the premium_status column
            $table->string('premium_status')->nullable()->default('free')->after('language');
        });

        // Update existing data: set premium_status based on has_premium
        DB::table('users')->where('has_premium', true)->update(['premium_status' => 'premium']);
        DB::table('users')->where('has_premium', false)->update(['premium_status' => 'free']);

        // Drop the has_premium column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_premium');
        });
    }
}; 