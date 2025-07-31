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
        // First, remove any duplicate pending offers (keep the most recent one)
        $duplicateOffers = DB::table('offers')
            ->where('status', 'pending')
            ->select('brand_id', 'creator_id', DB::raw('MAX(id) as max_id'))
            ->groupBy('brand_id', 'creator_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateOffers as $duplicate) {
            // Delete all but the most recent pending offer
            DB::table('offers')
                ->where('brand_id', $duplicate->brand_id)
                ->where('creator_id', $duplicate->creator_id)
                ->where('status', 'pending')
                ->where('id', '!=', $duplicate->max_id)
                ->delete();
        }

        // Add unique constraint for pending offers only using raw SQL
        DB::statement('CREATE UNIQUE INDEX unique_pending_offer_per_brand_creator ON offers (brand_id, creator_id) WHERE status = \'pending\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_pending_offer_per_brand_creator');
    }
};
