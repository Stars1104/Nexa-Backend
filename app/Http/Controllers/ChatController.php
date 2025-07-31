<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\UserOnlineStatus;
use App\Models\CampaignApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * Get user's chat rooms
     */
    public function getChatRooms(): JsonResponse
    {
        $user = Auth::user();
        $chatRooms = [];

        if ($user->isBrand()) {
            $chatRooms = ChatRoom::where('brand_id', $user->id)
                ->with(['creator', 'campaign', 'lastMessage.sender'])
                ->orderBy('last_message_at', 'desc')
                ->get();
        } elseif ($user->isCreator()) {
            $chatRooms = ChatRoom::where('creator_id', $user->id)
                ->with(['brand', 'campaign', 'lastMessage.sender'])
                ->orderBy('last_message_at', 'desc')
                ->get();
        }

        $formattedRooms = $chatRooms->map(function ($room) use ($user) {
            $otherUser = $user->isBrand() ? $room->creator : $room->brand;
            $lastMessage = $room->lastMessage->first();
            
            return [
                'id' => $room->id,
                'room_id' => $room->room_id,
                'campaign_id' => $room->campaign_id,
                'campaign_title' => $room->campaign->title,
                'other_user' => [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'avatar' => $otherUser->avatar_url,
                ],
                'last_message' => $lastMessage ? [
                    'id' => $lastMessage->id,
                    'message' => $lastMessage->message,
                    'message_type' => $lastMessage->message_type,
                    'sender_id' => $lastMessage->sender_id,
                    'is_sender' => $lastMessage->sender_id === $user->id,
                    'created_at' => $lastMessage->created_at->format('Y-m-d H:i:s'),
                ] : null,
                'unread_count' => $room->messages()
                    ->where('sender_id', '!=', $user->id)
                    ->where('is_read', false)
                    ->count(),
                'last_message_at' => $room->last_message_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedRooms,
        ]);
    }

    /**
     * Get messages for a specific chat room
     */
    public function getMessages(Request $request, string $roomId): JsonResponse
    {
        $user = Auth::user();

        \Log::info('Getting messages for room', [
            'room_id' => $roomId,
            'user_id' => $user->id,
        ]);

        // Find the chat room
        $room = ChatRoom::where('room_id', $roomId)
            ->where(function ($query) use ($user) {
                $query->where('brand_id', $user->id)
                      ->orWhere('creator_id', $user->id);
            })
            ->first();

        if (!$room) {
            \Log::error('Chat room not found for messages', [
                'room_id' => $roomId,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Chat room not found',
            ], 404);
        }

        \Log::info('Found chat room for messages', [
            'room_id' => $room->room_id,
            'chat_room_id' => $room->id,
            'brand_id' => $room->brand_id,
            'creator_id' => $room->creator_id,
        ]);

        // Mark messages as read for messages from other users
        $unreadMessages = $room->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->get();

        if ($unreadMessages->count() > 0) {
            $messageIds = $unreadMessages->pluck('id')->toArray();
            
            // Mark messages as read
            Message::whereIn('id', $messageIds)->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            \Log::info('Marked messages as read', [
                'message_ids' => $messageIds,
                'count' => count($messageIds),
            ]);
        }

        $messages = $room->messages()
            ->with('sender')
            ->orderBy('created_at', 'asc')
            ->get();

        \Log::info('Retrieved messages from database', [
            'room_id' => $roomId,
            'user_id' => $user->id,
            'total_messages' => $messages->count(),
            'message_ids' => $messages->pluck('id')->toArray(),
            'message_types' => $messages->pluck('message_type')->countBy()->toArray(),
        ]);

        $formattedMessages = $messages->map(function ($message) use ($user) {
            $messageData = [
                'id' => $message->id,
                'message' => $message->message,
                'message_type' => $message->message_type,
                'file_path' => $message->file_path,
                'file_name' => $message->file_name,
                'file_size' => $message->file_size,
                'file_type' => $message->file_type,
                'file_url' => $message->file_url,
                'formatted_file_size' => $message->formatted_file_size,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender->name,
                'sender_avatar' => $message->sender->avatar_url,
                'is_sender' => $message->sender_id === $user->id,
                'is_read' => $message->is_read,
                'read_at' => $message->read_at?->format('Y-m-d H:i:s'),
                'created_at' => $message->created_at->format('Y-m-d H:i:s'),
            ];

            // Add offer_data for offer messages
            if ($message->message_type === 'offer' && $message->offer_data) {
                // Handle both string and array types for offer_data
                $offerData = is_string($message->offer_data) ? json_decode($message->offer_data, true) : $message->offer_data;
                
                if ($offerData && is_array($offerData)) {
                    $messageData['offer_data'] = $offerData;
                    
                    // If offer is accepted and has contract_id, get contract information
                    if (isset($offerData['status']) && $offerData['status'] === 'accepted' && isset($offerData['contract_id'])) {
                        $contract = \App\Models\Contract::find($offerData['contract_id']);
                        if ($contract) {
                            $messageData['offer_data']['contract_status'] = $contract->status;
                            $messageData['offer_data']['can_be_completed'] = $contract->canBeCompleted();
                        }
                    }
                } else {
                    // Fallback if offer_data is invalid
                    $messageData['offer_data'] = null;
                }
            }

            return $messageData;
        });

        \Log::info('Returning formatted messages', [
            'room_id' => $roomId,
            'formatted_count' => $formattedMessages->count(),
            'formatted_message_ids' => $formattedMessages->pluck('id')->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'room' => [
                    'id' => $room->id,
                    'room_id' => $room->room_id,
                    'campaign_id' => $room->campaign_id,
                    'campaign_title' => $room->campaign->title,
                ],
                'messages' => $formattedMessages,
            ],
        ]);
    }

    /**
     * Send a message
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'required|string',
            'message' => 'required_without:file|string|max:1000',
            'file' => 'nullable|file|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        
        \Log::info('Sending message', [
            'room_id' => $request->room_id,
            'user_id' => $user->id,
            'message_length' => strlen($request->message ?? ''),
            'has_file' => $request->hasFile('file'),
        ]);
        
        $room = ChatRoom::where('room_id', $request->room_id)
            ->where(function ($query) use ($user) {
                $query->where('brand_id', $user->id)
                      ->orWhere('creator_id', $user->id);
            })
            ->first();

        if (!$room) {
            \Log::error('Chat room not found', [
                'room_id' => $request->room_id,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Chat room not found',
            ], 404);
        }

        \Log::info('Found chat room', [
            'room_id' => $room->room_id,
            'chat_room_id' => $room->id,
            'brand_id' => $room->brand_id,
            'creator_id' => $room->creator_id,
        ]);

        $messageData = [
            'chat_room_id' => $room->id,
            'sender_id' => $user->id,
            'message' => $request->message ?? '',
            'message_type' => 'text',
        ];

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('chat-files', $fileName, 'public');
            
            $messageData['message'] = $file->getClientOriginalName();
            $messageData['message_type'] = $this->getFileType($file->getMimeType());
            $messageData['file_path'] = $filePath;
            $messageData['file_name'] = $file->getClientOriginalName();
            $messageData['file_size'] = $file->getSize();
            $messageData['file_type'] = $file->getMimeType();
        }

        \Log::info('Creating message', $messageData);

        try {
            $message = Message::create($messageData);
            
            \Log::info('Message created successfully', [
                'message_id' => $message->id,
                'chat_room_id' => $message->chat_room_id,
                'sender_id' => $message->sender_id,
                'created_at' => $message->created_at,
            ]);

            // Update room's last message timestamp
            $room->update(['last_message_at' => now()]);

            // Load sender relationship
            $message->load('sender');

            $responseData = [
                'id' => $message->id,
                'message' => $message->message,
                'message_type' => $message->message_type,
                'file_path' => $message->file_path,
                'file_name' => $message->file_name,
                'file_size' => $message->file_size,
                'file_type' => $message->file_type,
                'file_url' => $message->file_url,
                'formatted_file_size' => $message->formatted_file_size,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender->name,
                'sender_avatar' => $message->sender->avatar_url,
                'is_sender' => true,
                'is_read' => false,
                'created_at' => $message->created_at->format('Y-m-d H:i:s'),
            ];

            // Add offer_data for offer messages
            if ($message->message_type === 'offer' && $message->offer_data) {
                // Handle both string and array types for offer_data
                $offerData = is_string($message->offer_data) ? json_decode($message->offer_data, true) : $message->offer_data;
                
                if ($offerData && is_array($offerData)) {
                    $responseData['offer_data'] = $offerData;
                    
                    // If offer is accepted and has contract_id, get contract information
                    if (isset($offerData['status']) && $offerData['status'] === 'accepted' && isset($offerData['contract_id'])) {
                        $contract = \App\Models\Contract::find($offerData['contract_id']);
                        if ($contract) {
                            $responseData['offer_data']['contract_status'] = $contract->status;
                            $responseData['offer_data']['can_be_completed'] = $contract->canBeCompleted();
                        }
                    }
                } else {
                    // Fallback if offer_data is invalid
                    $responseData['offer_data'] = null;
                }
            }

            \Log::info('Message sent successfully', [
                'message_id' => $message->id,
                'response_data' => $responseData,
            ]);

            return response()->json([
                'success' => true,
                'data' => $responseData,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error creating message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message_data' => $messageData,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message. Please try again.',
            ], 500);
        }
    }

    /**
     * Mark messages as read
     */
    public function markMessagesAsRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'required|string',
            'message_ids' => 'required|array',
            'message_ids.*' => 'integer|exists:messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        
        $room = ChatRoom::where('room_id', $request->room_id)
            ->where(function ($query) use ($user) {
                $query->where('brand_id', $user->id)
                      ->orWhere('creator_id', $user->id);
            })
            ->first();

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Chat room not found',
            ], 404);
        }

        // Mark messages as read
        Message::whereIn('id', $request->message_ids)
            ->where('chat_room_id', $room->id)
            ->where('sender_id', '!=', $user->id) // Only mark messages from other users
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read',
        ]);
    }

    /**
     * Create a chat room (when brand accepts creator proposal)
     */
    public function createChatRoom(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|integer|exists:campaigns,id',
            'creator_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can create chat rooms',
            ], 403);
        }

        // Check if application exists and is approved
        $application = CampaignApplication::where('campaign_id', $request->campaign_id)
            ->where('creator_id', $request->creator_id)
            ->where('status', 'approved')
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'No approved application found for this campaign and creator',
            ], 404);
        }

        // Create or find existing chat room
        $room = ChatRoom::findOrCreateRoom(
            $request->campaign_id,
            $user->id,
            $request->creator_id
        );

        return response()->json([
            'success' => true,
            'data' => [
                'room_id' => $room->room_id,
                'message' => 'Chat room created successfully',
            ],
        ]);
    }

    /**
     * Update typing status
     */
    public function updateTypingStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'required|string',
            'is_typing' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        
        $onlineStatus = UserOnlineStatus::firstOrCreate(['user_id' => $user->id]);
        $onlineStatus->setTypingInRoom($request->room_id, $request->is_typing);

        return response()->json([
            'success' => true,
            'message' => 'Typing status updated',
        ]);
    }

    /**
     * Get file type based on MIME type
     */
    private function getFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        return 'file';
    }
}
