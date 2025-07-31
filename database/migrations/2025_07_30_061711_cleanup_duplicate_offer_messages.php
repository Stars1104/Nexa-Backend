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
        // Clean up duplicate offer messages and fix status
        $messages = DB::table('messages')
            ->where('message_type', 'offer')
            ->get();

        foreach ($messages as $message) {
            $offerData = json_decode($message->offer_data, true);
            
            if ($offerData && is_array($offerData)) {
                // Fix null status to 'pending' for new offers
                if (!isset($offerData['status']) || $offerData['status'] === null) {
                    $offerData['status'] = 'pending';
                    
                    // Update the message with fixed status
                    DB::table('messages')
                        ->where('id', $message->id)
                        ->update(['offer_data' => json_encode($offerData)]);
                }
            }
        }

        // Remove duplicate offer messages (keep only the latest one for each offer)
        $duplicateMessages = DB::table('messages')
            ->where('message_type', 'offer')
            ->whereIn('message', ['Oferta enviada: R$ 50,00', 'Oferta enviada: R$ 30,00'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('message');

        foreach ($duplicateMessages as $messageText => $messages) {
            if ($messages->count() > 1) {
                // Keep the latest message, delete the older ones
                $latestMessage = $messages->first();
                $olderMessages = $messages->skip(1);
                
                foreach ($olderMessages as $oldMessage) {
                    DB::table('messages')->where('id', $oldMessage->id)->delete();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed for this cleanup
    }
};
