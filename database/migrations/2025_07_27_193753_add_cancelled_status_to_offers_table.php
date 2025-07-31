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
        // Drop the existing check constraint
        DB::statement("ALTER TABLE offers DROP CONSTRAINT offers_status_check");
        
        // Add the new check constraint with 'cancelled' status
        DB::statement("ALTER TABLE offers ADD CONSTRAINT offers_status_check CHECK (status = ANY (ARRAY['pending', 'accepted', 'rejected', 'expired', 'cancelled']))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new check constraint
        DB::statement("ALTER TABLE offers DROP CONSTRAINT offers_status_check");
        
        // Add back the original check constraint without 'cancelled'
        DB::statement("ALTER TABLE offers ADD CONSTRAINT offers_status_check CHECK (status = ANY (ARRAY['pending', 'accepted', 'rejected', 'expired']))");
    }
};
