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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->integer('min_age')->nullable()->after('max_bids');
            $table->integer('max_age')->nullable()->after('min_age');
            $table->json('target_genders')->nullable()->after('max_age'); // Array of genders or empty for no preference
            $table->json('target_creator_types')->nullable()->after('target_genders'); // Array of creator types, require at least one
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['min_age', 'max_age', 'target_genders', 'target_creator_types']);
        });
    }
}; 