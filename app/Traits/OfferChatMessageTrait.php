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

            // Load sender relationship for socket event
            $message->load('sender');

            // Emit socket event for real-time delivery
            $socketData = [
                'roomId' => $chatRoom->room_id,
                'messageId' => $message->id,
                'message' => $message->message,
                'senderId' => $message->sender_id,
                'senderName' => $message->sender ? $message->sender->name : 'System',
                'senderAvatar' => $message->sender ? $message->sender->avatar_url : null,
                'messageType' => $message->message_type,
                'fileData' => null,
                'offerData' => $data['offer_data'] ?? null,
                'timestamp' => $message->created_at->toISOString(),
            ];
            
            Log::info('Emitting socket event for offer message', $socketData);
            $this->emitSocketEvent('new_message', $socketData);

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

            // Emit socket event for real-time delivery
            $socketData = [
                'roomId' => $chatRoom->room_id,
                'messageId' => $systemMessage->id,
                'message' => $systemMessage->message,
                'senderId' => null,
                'senderName' => 'System',
                'senderAvatar' => null,
                'messageType' => $systemMessage->message_type,
                'fileData' => null,
                'offerData' => $data,
                'timestamp' => $systemMessage->created_at->toISOString(),
            ];
            
            Log::info('Emitting socket event for system message', $socketData);
            $this->emitSocketEvent('new_message', $socketData);

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

    /**
     * Emit Socket.IO event for real-time updates
     */
    private function emitSocketEvent(string $event, array $data): void
    {
        try {
            // Use HTTP POST to Node.js socket server
            \Illuminate\Support\Facades\Http::post('http://localhost:3000/emit', [
                'event' => $event,
                'data' => $data,
            ]);
            
            Log::info("Socket event emitted via HTTP: {$event}", $data);
        } catch (\Exception $e) {
            Log::error('Failed to emit socket event via HTTP', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
} 