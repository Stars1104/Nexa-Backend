<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if this migration has already been run
        if (DB::table('migrations')->where('migration', '2025_07_29_232513_clean_duplicate_offer_messages')->exists()) {
            Log::info('Migration clean_duplicate_offer_messages already run, skipping');
            return;
        }

        Log::info('Starting clean_duplicate_offer_messages migration');

        // Get all offer messages
        $offerMessages = DB::table('messages')
            ->where('message_type', 'offer')
            ->whereNotNull('offer_data')
            ->get();

        Log::info('Found ' . $offerMessages->count() . ' offer messages to process');

        $offerGroups = [];
        
        // Group messages by offer_id
        foreach ($offerMessages as $message) {
            $offerData = json_decode($message->offer_data, true);
            $offerId = $offerData['offer_id'] ?? null;
            
            if ($offerId) {
                if (!isset($offerGroups[$offerId])) {
                    $offerGroups[$offerId] = [];
                }
                $offerGroups[$offerId][] = $message;
            }
        }

        $totalDeleted = 0;

        // For each offer with multiple messages, keep only the most recent one
        foreach ($offerGroups as $offerId => $messages) {
            if (count($messages) > 1) {
                // Sort by created_at descending to get the most recent first
                usort($messages, function($a, $b) {
                    return strtotime($b->created_at) - strtotime($a->created_at);
                });
                
                // Keep the first (most recent) message, delete the rest
                $messagesToDelete = array_slice($messages, 1);
                
                foreach ($messagesToDelete as $messageToDelete) {
                    DB::table('messages')
                        ->where('id', $messageToDelete->id)
                        ->delete();
                    $totalDeleted++;
                }
                
                Log::info("Cleaned up " . count($messagesToDelete) . " duplicate messages for offer " . $offerId);
            }
        }

        Log::info("Migration completed. Total messages deleted: " . $totalDeleted);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as it deletes data
        // In a real scenario, you might want to backup the data first
        Log::warning('Cannot reverse clean_duplicate_offer_messages migration - data was permanently deleted');
    }
};
