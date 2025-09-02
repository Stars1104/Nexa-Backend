<?php

namespace App\Traits;

use App\Models\Message;
use App\Models\ChatRoom;
use Illuminate\Support\Facades\Log;

trait OfferChatMessageTrait
{
    /**
     * Create a chat message for offer-related events
     */
    private function createOfferChatMessage(ChatRoom $chatRoom, string $messageType, array $data = []): ?Message
    {
        try {
            $messageData = [
                'chat_room_id' => $chatRoom->id,
                'sender_id' => $data['sender_id'] ?? null, // System message
                'message' => $data['message'] ?? '',
                'message_type' => 'offer',
                'offer_data' => json_encode($data['offer_data'] ?? []),
            ];

            $message = Message::create($messageData);

            // Update chat room's last_message_at to ensure proper ordering
            $chatRoom->update(['last_message_at' => now()]);

            return $message;

        } catch (\Exception $e) {
            Log::error('Failed to create offer chat message', [
                'chat_room_id' => $chatRoom->id,
                'message_type' => $messageType,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Create a system message for contract-related events
     */
    private function createSystemMessage(ChatRoom $chatRoom, string $message, array $data = []): ?Message
    {
        try {
            $messageData = [
                'chat_room_id' => $chatRoom->id,
                'sender_id' => null, // System message
                'message' => $message,
                'message_type' => 'system',
                'offer_data' => json_encode($data),
            ];

            $systemMessage = Message::create($messageData);

            // Update chat room's last_message_at to ensure proper ordering
            $chatRoom->update(['last_message_at' => now()]);

            return $systemMessage;

        } catch (\Exception $e) {
            Log::error('Failed to create system message', [
                'chat_room_id' => $chatRoom->id,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }
} 