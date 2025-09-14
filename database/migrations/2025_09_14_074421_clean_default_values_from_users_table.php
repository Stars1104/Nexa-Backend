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
        // First, make birth_date nullable
        DB::statement('ALTER TABLE users ALTER COLUMN birth_date DROP NOT NULL');
        
        // Clean up default values that were set incorrectly
        DB::statement("UPDATE users SET birth_date = NULL WHERE birth_date = '1990-01-01'");
        DB::statement("UPDATE users SET languages = NULL WHERE languages::text = '[\"English\"]'");
        DB::statement("UPDATE users SET language = NULL WHERE language = 'English'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as we're cleaning up data
        // If needed, you would need to restore the default values manually
    }
};