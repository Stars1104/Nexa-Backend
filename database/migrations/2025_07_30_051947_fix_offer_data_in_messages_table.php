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
        // Fix offer_data that are stored as JSON strings instead of proper JSON
        $messages = DB::table('messages')
            ->where('message_type', 'offer')
            ->whereNotNull('offer_data')
            ->get();

        foreach ($messages as $message) {
            $offerData = $message->offer_data;
            
            // If offer_data is a string, try to decode it
            if (is_string($offerData)) {
                $decoded = json_decode($offerData, true);
                if ($decoded !== null && is_array($decoded)) {
                    // Update with properly formatted JSON
                    DB::table('messages')
                        ->where('id', $message->id)
                        ->update(['offer_data' => json_encode($decoded)]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed for this data fix
    }
};
