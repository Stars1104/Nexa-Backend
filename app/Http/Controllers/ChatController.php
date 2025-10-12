<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\UserOnlineStatus;
use App\Models\CampaignApplication;
use App\Traits\OfferChatMessageTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    use OfferChatMessageTrait;
    /**
     * Get user's chat rooms
     */
    public function getChatRooms(): JsonResponse
    {
        $user = Auth::user();
        
        \Log::info('Getting chat rooms', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'is_brand' => $user->isBrand(),
            'is_creator' => $user->isCreator(),
            'is_admin' => $user->isAdmin(),
        ]);
        
        $chatRooms = collect();

        if ($user->isBrand()) {
            $chatRooms = ChatRoom::where('brand_id', $user->id)
                ->with(['creator', 'campaign', 'lastMessage.sender'])
                ->orderBy('created_at', 'desc') // Newest rooms first
                ->orderBy('last_message_at', 'desc') // Then by last message
                ->get();
                
            \Log::info('Found chat rooms for brand', [
                'brand_id' => $user->id,
                'room_count' => $chatRooms->count(),
                'room_ids' => $chatRooms->pluck('room_id')->toArray(),
            ]);
        } elseif ($user->isCreator() || $user->isStudent()) {
            $chatRooms = ChatRoom::where('creator_id', $user->id)
                ->with(['brand', 'campaign', 'lastMessage.sender'])
                ->orderBy('created_at', 'desc') // Newest rooms first
                ->orderBy('last_message_at', 'desc') // Then by last message
                ->get();
                
            \Log::info('Found chat rooms for creator/student', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'room_count' => $chatRooms->count(),
                'room_ids' => $chatRooms->pluck('room_id')->toArray(),
            ]);
        } elseif ($user->isAdmin()) {
            // Admin can see all chat rooms
            $chatRooms = ChatRoom::with(['creator', 'brand', 'campaign', 'lastMessage.sender'])
                ->orderBy('created_at', 'desc') // Newest rooms first
                ->orderBy('last_message_at', 'desc') // Then by last message
                ->get();
                
            \Log::info('Found chat rooms for admin', [
                'admin_id' => $user->id,
                'room_count' => $chatRooms->count(),
                'room_ids' => $chatRooms->pluck('room_id')->toArray(),
            ]);
        }

        $formattedRooms = $chatRooms->map(function ($room) use ($user) {
            $otherUser = $user->isBrand() ? $room->creator : $room->brand;
            $lastMessage = $room->lastMessage->first();
            
            // For admin users, determine which user they're chatting with based on the last message
            if ($user->isAdmin()) {
                if ($lastMessage && $lastMessage->sender_id === $room->brand_id) {
                    $otherUser = $room->brand;
                } elseif ($lastMessage && $lastMessage->sender_id === $room->creator_id) {
                    $otherUser = $room->creator;
                } else {
                    // If no messages, default to showing the creator, but fallback to brand if creator is null
                    $otherUser = $room->creator ?? $room->brand;
                }
            }
            
            // Additional safety check - if otherUser is still null, skip this room
            if (!$otherUser) {
                \Log::warning('Skipping chat room with null other user', [
                    'room_id' => $room->room_id,
                    'brand_id' => $room->brand_id,
                    'creator_id' => $room->creator_id,
                    'user_id' => $user->id,
                ]);
                return null;
            }
            
            return [
                'id' => $room->id,
                'room_id' => $room->room_id,
                'campaign_id' => $room->campaign_id,
                'campaign_title' => $room->campaign?->title ?? 'Campaign Not Found',
                'campaign_status' => $room->campaign?->status ?? 'unknown',
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
                    'created_at' => $lastMessage->created_at->toISOString(),
                ] : null,
                'unread_count' => $room->messages()
                    ->where('sender_id', '!=', $user->id)
                    ->where('is_read', false)
                    ->count(),
                'last_message_at' => $room->last_message_at?->toISOString(),
            ];
        })->filter(); // Remove null values

        return response()->json([
            'success' => true,
            'data' => $formattedRooms->values(), // Reset array keys
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
        if ($user->isAdmin()) {
            // Admin can access any chat room
            $room = ChatRoom::where('room_id', $roomId)->first();
        } else {
            // Regular users can only access their own chat rooms
            $room = ChatRoom::where('room_id', $roomId)
                ->where(function ($query) use ($user) {
                    $query->where('brand_id', $user->id)
                          ->orWhere('creator_id', $user->id);
                })
                ->first();
        }

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

        // If this is a brand opening the chat for the first time, automatically send initial offer
        if ($user->isBrand() && $room->campaign_id && !$room->messages()->exists()) {
            $this->sendInitialOfferIfNeeded($room);
        }

        // Also check if we need to send initial offer when loading messages
        if ($user->isBrand() && $room->campaign_id && $room->messages()->count() === 0) {
            $this->sendInitialOfferIfNeeded($room);
        }

        // Also trigger for creators/students when they first load the chat
        if (($user->isCreator() || $user->isStudent()) && $room->campaign_id && $room->messages()->count() === 0) {
            $this->sendInitialOfferIfNeeded($room);
        }

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

        // Check for messages with null senders and log them
        $nullSenderMessages = $messages->filter(function ($message) {
            return $message->sender === null;
        });
        
        if ($nullSenderMessages->count() > 0) {
            \Log::warning('Found messages with null senders', [
                'room_id' => $roomId,
                'null_sender_message_ids' => $nullSenderMessages->pluck('id')->toArray(),
                'null_sender_user_ids' => $nullSenderMessages->pluck('sender_id')->toArray(),
            ]);
        }

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
                'sender_name' => $message->sender ? $message->sender->name : 'Unknown User',
                'sender_avatar' => $message->sender ? $message->sender->avatar_url : null,
                'is_sender' => $message->sender_id === $user->id,
                'is_read' => $message->is_read,
                'read_at' => $message->read_at?->toISOString(),
                'created_at' => $message->created_at->toISOString(),
            ];

            // Add offer_data for offer messages and contract completion messages
            if (($message->message_type === 'offer' || $message->message_type === 'contract_completion') && $message->offer_data) {
                // Handle both string and array types for offer_data
                $offerData = is_string($message->offer_data) ? json_decode($message->offer_data, true) : $message->offer_data;
                
                if ($offerData && is_array($offerData)) {
                    // Get the current offer status from the database
                    if (isset($offerData['offer_id'])) {
                        $currentOffer = \App\Models\Offer::find($offerData['offer_id']);
                        if ($currentOffer) {
                            // Update the offer data with current status
                            $offerData['status'] = $currentOffer->status;
                            $offerData['accepted_at'] = $currentOffer->accepted_at?->format('Y-m-d H:i:s');
                            $offerData['rejected_at'] = $currentOffer->rejected_at?->format('Y-m-d H:i:s');
                            $offerData['rejection_reason'] = $currentOffer->rejection_reason;
                            
                            // If offer is accepted and has contract, get contract information
                            if ($currentOffer->status === 'accepted') {
                                // Load the contract relationship explicitly
                                $contract = \App\Models\Contract::where('offer_id', $currentOffer->id)->first();
                                
                                if ($contract) {
                                    $offerData['contract_id'] = $contract->id;
                                    $offerData['contract_status'] = $contract->status;
                                    $offerData['can_be_completed'] = $contract->canBeCompleted();
                                    
                                    \Log::info('Contract data included in offer message', [
                                        'offer_id' => $currentOffer->id,
                                        'contract_id' => $contract->id,
                                        'contract_status' => $contract->status,
                                        'can_be_completed' => $contract->canBeCompleted(),
                                    ]);
                                } else {
                                    \Log::warning('No contract found for accepted offer', [
                                        'offer_id' => $currentOffer->id,
                                        'offer_status' => $currentOffer->status,
                                    ]);
                                }
                            } else {
                                // Log when contract data is missing
                                \Log::info('No contract data for offer', [
                                    'offer_id' => $currentOffer->id,
                                    'offer_status' => $currentOffer->status,
                                ]);
                            }
                        }
                    }
                    
                    $messageData['offer_data'] = $offerData;
                    
                    // Log contract completion message data for debugging
                    if ($message->message_type === 'contract_completion') {
                        \Log::info('Contract completion message offer_data included', [
                            'message_id' => $message->id,
                            'message_type' => $message->message_type,
                            'offer_data' => $offerData,
                        ]);
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
        
        if ($user->isAdmin()) {
            // Admin can send messages to any chat room
            $room = ChatRoom::where('room_id', $request->room_id)->first();
        } else {
            // Regular users can only send messages to their own chat rooms
            $room = ChatRoom::where('room_id', $request->room_id)
                ->where(function ($query) use ($user) {
                    $query->where('brand_id', $user->id)
                          ->orWhere('creator_id', $user->id);
                })
                ->first();
        }

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
                'sender_name' => $message->sender ? $message->sender->name : 'Unknown User',
                'sender_avatar' => $message->sender ? $message->sender->avatar_url : null,
                'is_sender' => true,
                'is_read' => false,
                'created_at' => $message->created_at->toISOString(),
            ];

            // Add offer_data for offer messages and contract completion messages
            if (($message->message_type === 'offer' || $message->message_type === 'contract_completion') && $message->offer_data) {
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

            // Emit socket event for real-time delivery
            $socketData = [
                'roomId' => $room->room_id,
                'messageId' => $message->id,
                'message' => $message->message,
                'senderId' => $message->sender_id,
                'senderName' => $message->sender ? $message->sender->name : 'Unknown User',
                'senderAvatar' => $message->sender ? $message->sender->avatar_url : null,
                'messageType' => $message->message_type,
                'fileData' => $message->file_path ? [
                    'file_path' => $message->file_path,
                    'file_name' => $message->file_name,
                    'file_size' => $message->file_size,
                    'file_type' => $message->file_type,
                    'file_url' => $message->file_url,
                ] : null,
                'offerData' => $message->offer_data ? json_decode($message->offer_data, true) : null,
                'timestamp' => $message->created_at->toISOString(),
            ];
            
            \Log::info('Emitting socket event for message', $socketData);
            $this->emitSocketEvent('new_message', $socketData);

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
        
        if ($user->isAdmin()) {
            // Admin can mark messages as read in any chat room
            $room = ChatRoom::where('room_id', $request->room_id)->first();
        } else {
            // Regular users can only mark messages as read in their own chat rooms
            $room = ChatRoom::where('room_id', $request->room_id)
                ->where(function ($query) use ($user) {
                    $query->where('brand_id', $user->id)
                          ->orWhere('creator_id', $user->id);
                })
                ->first();
        }

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
        
        if (!$user->isBrand() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands and admins can create chat rooms',
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

        // Update application workflow status to indicate first contact has been initiated
        if ($room->wasRecentlyCreated) {
            $application->initiateFirstContact();
            
            \Log::info('Application workflow status updated to agreement_in_progress', [
                'application_id' => $application->id,
                'campaign_id' => $request->campaign_id,
                'creator_id' => $request->creator_id,
                'workflow_status' => $application->workflow_status,
            ]);
            
            // Send initial offer automatically when chat room is created
            $this->sendInitialOfferIfNeeded($room);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'room_id' => $room->room_id,
                'message' => 'Chat room created successfully',
                'workflow_status_updated' => $room->wasRecentlyCreated,
            ],
        ]);
    }

    /**
     * Send guide messages when user first enters chat
     */
    public function sendGuideMessages(Request $request, string $roomId): JsonResponse
    {
        $user = Auth::user();
        
        Log::info('sendGuideMessages called', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'room_id' => $roomId,
        ]);
        
        // Find the chat room and verify user has access
        $room = ChatRoom::where('room_id', $roomId)
            ->where(function ($query) use ($user) {
                $query->where('brand_id', $user->id)
                      ->orWhere('creator_id', $user->id);
            })
            ->first();

        if (!$room) {
            Log::error('Chat room not found', [
                'user_id' => $user->id,
                'room_id' => $roomId,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Chat room not found or access denied',
            ], 404);
        }

        try {
            // Check if guide messages already exist for this user in this room
            $existingGuideMessages = Message::where('chat_room_id', $room->id)
                ->where('sender_id', $user->id)
                ->where('message_type', 'system')
                ->where('is_system_message', true)
                ->exists();

            if ($existingGuideMessages) {
                return response()->json([
                    'success' => true,
                    'message' => 'Guide messages already sent',
                ]);
            }

            // Get the other user in the chat
            $otherUser = $user->isBrand() ? $room->creator : $room->brand;

            if ($user->isBrand()) {
                // Message for brand
                $brandMessage = "🎉 **Parabéns pela parceria iniciada!**\n\n" .
                    "Você acaba de conectar com uma criadora talentosa da nossa plataforma. Para garantir o melhor resultado possível, é essencial orientar com detalhamento e clareza.\n\n" .
                    "**📋 Próximos Passos Importantes:**\n\n" .
                    "• **Saldo:** Insira o valor da campanha na aba \"Saldo\" da plataforma\n" .
                    "• **Pagamento:** Libere o pagamento após aprovar o conteúdo final\n" .
                    "• **Briefing:** Reforce os pontos principais com a criadora\n" .
                    "• **Ajustes:** Até 2 pedidos de ajustes por vídeo são permitidos\n\n" .
                    "**🔒 Regras de Segurança:**\n\n" .
                    "✅ **Comunicação:** Exclusivamente pelo chat da NEXA\n" .
                    "❌ **Dados:** Não compartilhe informações bancárias ou pessoais\n" .
                    "⚠️ **Prazos:** Descumprimento pode resultar em advertência\n" .
                    "🚫 **Cancelamento:** Produtos devem ser devolvidos se necessário\n\n" .
                    "A NEXA está aqui para facilitar conexões seguras e profissionais! 💼✨";

                Message::create([
                    'chat_room_id' => $room->id,
                    'sender_id' => $user->id,
                    'message' => $brandMessage,
                    'message_type' => 'system',
                    'is_system_message' => true,
                ]);
            } else {
                // Message for creator
                $creatorMessage = "🎉 **Parabéns! Você foi aprovada!**\n\n" .
                    "Estamos muito felizes em contar com você! Mostre toda sua criatividade, comprometimento e qualidade para representar bem a marca e nossa plataforma.\n\n" .
                    "**📋 Checklist de Sucesso:**\n\n" .
                    "• **Endereço:** Confirme seu endereço de envio o quanto antes\n" .
                    "• **Roteiro:** Entregue em até 5 dias úteis\n" .
                    "• **Briefing:** Siga todas as orientações da marca\n" .
                    "• **Aprovação:** Aguarde aprovação do roteiro antes de gravar\n" .
                    "• **Conteúdo:** Entregue o vídeo final em até 5 dias úteis após aprovação\n" .
                    "• **Qualidade:** Vídeo profissional, até 2 ajustes permitidos\n" .
                    "• **Comunicação:** Mantenha retorno rápido no chat\n\n" .
                    "**🔒 Regras Importantes:**\n\n" .
                    "✅ **Chat:** Comunicação exclusivamente pela NEXA\n" .
                    "❌ **Dados:** Não compartilhe informações bancárias ou pessoais\n" .
                    "⚠️ **Prazos:** Descumprimento pode resultar em penalizações\n" .
                    "🚫 **Cancelamento:** Produtos devem ser devolvidos se necessário\n\n" .
                    "Estamos aqui para garantir a melhor experiência! Boa campanha! 💼💡";

                Message::create([
                    'chat_room_id' => $room->id,
                    'sender_id' => $user->id,
                    'message' => $creatorMessage,
                    'message_type' => 'system',
                    'is_system_message' => true,
                ]);
            }

            // Send automatic status message
            $statusMessage = "💼 **Detalhes da Campanha**\n\n" .
                "**Status:** 🟢 Conectado\n\n" .
                "Você está agora conectado e pode começar a conversar!\n" .
                "Use o chat para todas as comunicações e siga as diretrizes da plataforma.";

            Message::create([
                'chat_room_id' => $room->id,
                'sender_id' => $user->id,
                'message' => $statusMessage,
                'message_type' => 'system',
                'is_system_message' => true,
            ]);

            Log::info('Guide messages sent successfully', [
                'chat_room_id' => $room->id,
                'user_id' => $user->id,
                'user_role' => $user->role,
                'messages_created' => 3, // We create 3 messages: guide message + quote message
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Guide messages sent successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send guide messages', [
                'chat_room_id' => $room->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send guide messages',
            ], 500);
        }
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

    /**
     * Send initial offer automatically when chat opens for the first time
     */
    public function sendInitialOfferIfNeeded(ChatRoom $chatRoom): void
    {
        try {
            $campaign = $chatRoom->campaign;
            if (!$campaign) {
                return;
            }

            // Check if there's already an offer for this campaign in this chat room
            $existingOffer = \App\Models\Offer::where('campaign_id', $campaign->id)
                ->where('chat_room_id', $chatRoom->id)
                ->first();

            if ($existingOffer) {
                return; // Offer already exists (regardless of status)
            }

            $isBarter = $campaign->remuneration_type === 'permuta';
            $budget = $isBarter ? 0 : $campaign->budget;
            
            // Calculate estimated days from campaign deadline
            $estimatedDays = now()->diffInDays($campaign->deadline, false);
            if ($estimatedDays <= 0) {
                $estimatedDays = 30; // Fallback if deadline is in the past
            }
            
            // Use campaign deadline for offer expiration
            $expiresAt = $campaign->deadline;
            if ($expiresAt->isPast()) {
                $expiresAt = now()->addDays(7); // Fallback if deadline is in the past
            }
            
            $offer = \App\Models\Offer::create([
                'brand_id' => $chatRoom->brand_id,
                'creator_id' => $chatRoom->creator_id,
                'campaign_id' => $campaign->id,
                'chat_room_id' => $chatRoom->id,
                'title' => $isBarter ? 'Oferta de Permuta' : 'Oferta de Projeto',
                'description' => $isBarter ? 'Oferta de permuta baseada na campanha criada' : 'Oferta baseada na campanha criada',
                'budget' => $budget,
                'estimated_days' => $estimatedDays,
                'requirements' => $campaign->requirements ?? [],
                'is_barter' => $isBarter,
                'barter_description' => $isBarter ? 'Permuta baseada na campanha: ' . $campaign->title : null,
                'expires_at' => $expiresAt,
            ]);

            // Create chat message for the initial offer
            $this->createOfferChatMessage($chatRoom, 'offer_created', [
                'sender_id' => $chatRoom->brand_id,
                'message' => $isBarter 
                    ? "Oferta de permuta enviada automaticamente (Prazo: {$offer->estimated_days} dias)" 
                    : "Oferta enviada automaticamente: {$offer->formatted_budget} (Prazo: {$offer->estimated_days} dias)",
                'offer_data' => [
                    'offer_id' => $offer->id,
                    'title' => $offer->title,
                    'description' => $offer->description,
                    'budget' => $offer->formatted_budget,
                    'formatted_budget' => $offer->formatted_budget,
                    'estimated_days' => $offer->estimated_days,
                    'status' => 'pending',
                    'expires_at' => $offer->expires_at->toISOString(),
                    'days_until_expiry' => $offer->days_until_expiry,
                    'is_expiring_soon' => $offer->is_expiring_soon,
                    'created_at' => $offer->created_at->toISOString(),
                    'is_barter' => $isBarter,
                    'barter_description' => $offer->barter_description,
                    'can_be_accepted' => true,
                    'can_be_rejected' => true,
                    'can_be_cancelled' => true,
                    'sender' => [
                        'id' => $chatRoom->brand->id,
                        'name' => $chatRoom->brand->name,
                        'avatar_url' => $chatRoom->brand->avatar_url,
                    ],
                ],
            ]);

            \Log::info('Initial offer sent automatically', [
                'chat_room_id' => $chatRoom->id,
                'offer_id' => $offer->id,
                'is_barter' => $isBarter,
                'campaign_id' => $campaign->id,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error sending initial offer automatically', [
                'error' => $e->getMessage(),
                'chat_room_id' => $chatRoom->id,
                'campaign_id' => $chatRoom->campaign_id,
            ]);
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
            
            \Log::info("Socket event emitted via HTTP: {$event}", $data);
        } catch (\Exception $e) {
            \Log::error('Failed to emit socket event via HTTP', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
