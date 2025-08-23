<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\UserOnlineStatus;
use App\Models\CampaignApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
                ->orderBy('last_message_at', 'desc')
                ->get();
                
            \Log::info('Found chat rooms for brand', [
                'brand_id' => $user->id,
                'room_count' => $chatRooms->count(),
                'room_ids' => $chatRooms->pluck('room_id')->toArray(),
            ]);
        } elseif ($user->isCreator()) {
            $chatRooms = ChatRoom::where('creator_id', $user->id)
                ->with(['brand', 'campaign', 'lastMessage.sender'])
                ->orderBy('last_message_at', 'desc')
                ->get();
                
            \Log::info('Found chat rooms for creator', [
                'creator_id' => $user->id,
                'room_count' => $chatRooms->count(),
                'room_ids' => $chatRooms->pluck('room_id')->toArray(),
            ]);
        } elseif ($user->isAdmin()) {
            // Admin can see all chat rooms
            $chatRooms = ChatRoom::with(['creator', 'brand', 'campaign', 'lastMessage.sender'])
                ->orderBy('last_message_at', 'desc')
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
                    // If no messages, default to showing the creator
                    $otherUser = $room->creator;
                }
            }
            
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
}
