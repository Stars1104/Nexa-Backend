<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class ContractController extends Controller
{
    /**
     * Emit Socket.IO event for real-time updates
     */
    private function emitSocketEvent(string $event, array $data): void
    {
        try {
            if (isset($GLOBALS['socket_server'])) {
                $io = $GLOBALS['socket_server'];
                $io->emit($event, $data);
                Log::info("Socket event emitted: {$event}", $data);
            } else {
                Log::warning("Socket server not available for event: {$event}");
            }
        } catch (\Exception $e) {
            Log::error('Failed to emit socket event', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send automatic approval messages to both parties
     */
    private function sendApprovalMessages($contract): void
    {
        try {
            $chatRoom = $contract->offer->chatRoom;
            $brand = $contract->brand;
            $creator = $contract->creator;

            // Message for creator
            $creatorMessage = "🩷 Parabéns, você foi aprovada em mais uma campanha da NEXA!\n\n" .
                "Estamos muito felizes em contar com você e esperamos que mostre toda sua criatividade, comprometimento e qualidade para representar bem a marca e a nossa plataforma.\n\n" .
                "Antes de começar, fique atenta aos pontos abaixo para garantir uma parceria de sucesso:\n\n" .
                "• Confirme seu endereço de envio o quanto antes, para que o produto possa ser encaminhado sem atrasos.\n" .
                "• Você devera entregar o roteiro da campanha em até 5 dias úteis.\n" .
                "• É essencial seguir todas as orientações da marca presentes no briefing.\n" .
                "• Aguarde a aprovação do roteiro antes de gravar o conteúdo.\n" .
                "• Após a aprovação do roteiro, o conteúdo final deve ser entregue em até 5 dias úteis.\n" .
                "• O vídeo deve ser enviado com qualidade profissional, e poderá passar por até 2 solicitações de ajustes, caso não esteja conforme o briefing.\n" .
                "• Pedimos que mantenha o retorno rápido nas mensagens dentro do chat da plataforma.\n\n" .
                "Atenção para algumas regras importantes:\n\n" .
                "✔ Toda a comunicação deve acontecer exclusivamente pelo chat da Anexa.\n" .
                "✘ Não é permitido compartilhar dados bancários, e-mails ou número de WhatsApp dentro da plataforma.\n" .
                "⚠️ O não cumprimento dos prazos ou regras pode acarretar em penalizações ou banimento.\n" .
                "🚫 Caso a campanha seja cancelada, o produto deverá ser devolvido, e a criadora poderá ser punida.\n\n" .
                "Estamos aqui para garantir a melhor experiência para criadoras e marcas. Boa campanha! 💼💡";

            // Message for brand
            $brandMessage = "🎉 **Parabéns pela parceria iniciada com uma criadora da nossa plataforma!**\n\n" .
                "Para garantir o melhor resultado possível, é essencial que você oriente a criadora com detalhamento e clareza sobre como deseja que o conteúdo seja feito. **Quanto mais específica for a comunicação, maior será a qualidade da entrega.**\n\n" .
                "**📋 Próximos Passos Importantes:**\n\n" .
                "• **💰 Saldo da Campanha:** Insira o valor da campanha na aba \"Saldo\" da plataforma\n" .
                "• **✅ Aprovação de Conteúdo:** Avalie o roteiro antes da gravação para garantir alinhamento\n" .
                "• **🎬 Entrega Final:** Após receber o conteúdo pronto e editado, libere o pagamento\n" .
                "• **⭐ Finalização:** Clique em \"Finalizar Campanha\" e avalie o trabalho entregue\n" .
                "• **📝 Briefing:** Reforce os pontos principais com a criadora para alinhar com o objetivo da marca\n" .
                "• **🔄 Ajustes:** Permita até 2 pedidos de ajustes por vídeo caso necessário\n\n" .
                "**🔒 Regras de Segurança da Campanha:**\n\n" .
                "✅ **Comunicação Exclusiva:** Toda comunicação deve ser feita pelo chat da NEXA\n" .
                "❌ **Proteção de Dados:** Não compartilhe dados bancários, contatos pessoais ou WhatsApp\n" .
                "⚠️ **Cumprimento de Prazos:** Descumprimento pode resultar em advertência ou bloqueio\n" .
                "🚫 **Cancelamento:** Em caso de cancelamento, o produto deve ser solicitado de volta\n\n" .
                "**💼 A NEXA está aqui para facilitar conexões seguras e profissionais!**\n" .
                "Conte conosco para apoiar o sucesso da sua campanha! 📢✨";

            // Create messages in the chat room
            \App\Models\Message::create([
                'chat_room_id' => $chatRoom->id,
                'sender_id' => $brand->id,
                'message' => $creatorMessage,
                'message_type' => 'text',
                'is_system_message' => true,
            ]);

            \App\Models\Message::create([
                'chat_room_id' => $chatRoom->id,
                'sender_id' => $brand->id,
                'message' => $brandMessage,
                'message_type' => 'text',
                'is_system_message' => true,
            ]);

            // Send automatic quote message
            $quoteMessage = "💼 **Detalhes da Campanha:**\n\n" .
                "**Orçamento:** {$contract->formatted_budget}\n" .
                "**Duração:** {$contract->estimated_days} dias\n" .
                "**Status:** 🟢 Ativa\n\n" .
                "A campanha está agora ativa e ambas as partes podem começar a trabalhar juntas. **Use o chat para todas as comunicações** e siga as diretrizes da plataforma para uma parceria de sucesso.";

            \App\Models\Message::create([
                'chat_room_id' => $chatRoom->id,
                'sender_id' => $brand->id,
                'message' => $quoteMessage,
                'message_type' => 'text',
                'is_system_message' => true,
            ]);

            Log::info('Approval messages sent successfully', [
                'contract_id' => $contract->id,
                'chat_room_id' => $chatRoom->id,
                'brand_id' => $brand->id,
                'creator_id' => $creator->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send approval messages', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Get contracts for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $status = $request->get('status'); // 'active', 'completed', 'cancelled', 'disputed'

        try {
            $query = $user->isBrand() 
                ? $user->brandContracts() 
                : $user->creatorContracts();

            if ($status) {
                $query->where('status', $status);
            }

            $contracts = $query->with(['brand:id,name,avatar_url', 'creator:id,name,avatar_url', 'offer'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            $contracts->getCollection()->transform(function ($contract) use ($user) {
                $otherUser = $user->isBrand() ? $contract->creator : $contract->brand;
                
                return [
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'description' => $contract->description,
                    'budget' => $contract->formatted_budget,
                    'creator_amount' => $contract->formatted_creator_amount,
                    'platform_fee' => $contract->formatted_platform_fee,
                    'estimated_days' => $contract->estimated_days,
                    'requirements' => $contract->requirements,
                    'status' => $contract->status,
                    'workflow_status' => $contract->workflow_status,
                    'started_at' => $contract->started_at->format('Y-m-d H:i:s'),
                    'expected_completion_at' => $contract->expected_completion_at->format('Y-m-d H:i:s'),
                    'completed_at' => $contract->completed_at?->format('Y-m-d H:i:s'),
                    'cancelled_at' => $contract->cancelled_at?->format('Y-m-d H:i:s'),
                    'cancellation_reason' => $contract->cancellation_reason,
                    'days_until_completion' => $contract->days_until_completion,
                    'progress_percentage' => $contract->progress_percentage,
                    'is_overdue' => $contract->isOverdue(),
                    'is_near_completion' => $contract->is_near_completion,
                    'can_be_completed' => $contract->canBeCompleted(),
                    'can_be_cancelled' => $contract->canBeCancelled(),
                    'can_be_started' => $contract->canBeStarted(),
                    'is_waiting_for_review' => $contract->isWaitingForReview(),
                    'is_payment_available' => $contract->isPaymentAvailable(),
                    'is_payment_withdrawn' => $contract->isPaymentWithdrawn(),
                    'has_brand_review' => $contract->has_brand_review,
                    'has_creator_review' => $contract->has_creator_review,
                    'has_both_reviews' => $contract->has_both_reviews,
                    'creator' => [
                        'id' => $contract->creator->id,
                        'name' => $contract->creator->name,
                        'avatar_url' => $contract->creator->avatar_url,
                    ],
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar_url' => $otherUser->avatar_url,
                    ],
                    'payment' => $contract->payment ? [
                        'id' => $contract->payment->id,
                        'status' => $contract->payment->status,
                        'total_amount' => $contract->payment->formatted_total_amount,
                        'creator_amount' => $contract->payment->formatted_creator_amount,
                        'platform_fee' => $contract->payment->formatted_platform_fee,
                        'processed_at' => $contract->payment->processed_at?->format('Y-m-d H:i:s'),
                    ] : null,
                    'review' => $contract->review ? [
                        'id' => $contract->review->id,
                        'rating' => $contract->review->rating,
                        'comment' => $contract->review->comment,
                        'created_at' => $contract->review->created_at->format('Y-m-d H:i:s'),
                    ] : null,
                    'created_at' => $contract->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $contracts,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching contracts', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contracts',
            ], 500);
        }
    }

    /**
     * Get a specific contract
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $contract = Contract::with(['brand:id,name,avatar_url', 'creator:id,name,avatar_url', 'offer'])
                ->where(function ($query) use ($user) {
                    $query->where('brand_id', $user->id)
                          ->orWhere('creator_id', $user->id);
                })
                ->find($id);

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato não encontrado ou acesso negado',
                ], 404);
            }

            $otherUser = $user->isBrand() ? $contract->creator : $contract->brand;
            
            $contractData = [
                'id' => $contract->id,
                'title' => $contract->title,
                'description' => $contract->description,
                'budget' => $contract->formatted_budget,
                'creator_amount' => $contract->formatted_creator_amount,
                'platform_fee' => $contract->formatted_platform_fee,
                'estimated_days' => $contract->estimated_days,
                'requirements' => $contract->requirements,
                'status' => $contract->status,
                'workflow_status' => $contract->workflow_status,
                'started_at' => $contract->started_at->format('Y-m-d H:i:s'),
                'expected_completion_at' => $contract->expected_completion_at->format('Y-m-d H:i:s'),
                'completed_at' => $contract->completed_at?->format('Y-m-d H:i:s'),
                'cancelled_at' => $contract->cancelled_at?->format('Y-m-d H:i:s'),
                'cancellation_reason' => $contract->cancellation_reason,
                'days_until_completion' => $contract->days_until_completion,
                'progress_percentage' => $contract->progress_percentage,
                'is_overdue' => $contract->isOverdue(),
                'is_near_completion' => $contract->is_near_completion,
                'can_be_completed' => $contract->can_be_completed,
                'can_be_cancelled' => $contract->can_be_cancelled,
                'is_waiting_for_review' => $contract->isWaitingForReview(),
                'is_payment_available' => $contract->isPaymentAvailable(),
                'is_payment_withdrawn' => $contract->isPaymentWithdrawn(),
                'has_brand_review' => $contract->has_brand_review,
                'has_creator_review' => $contract->has_creator_review,
                'has_both_reviews' => $contract->has_both_reviews,
                'creator' => [
                    'id' => $contract->creator->id,
                    'name' => $contract->creator->name,
                    'avatar_url' => $contract->creator->avatar_url,
                ],
                'other_user' => [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'avatar_url' => $otherUser->avatar_url,
                ],
                'payment' => $contract->payment ? [
                    'id' => $contract->payment->id,
                    'status' => $contract->payment->status,
                    'total_amount' => $contract->payment->formatted_total_amount,
                    'creator_amount' => $contract->payment->formatted_creator_amount,
                    'platform_fee' => $contract->payment->formatted_platform_fee,
                    'processed_at' => $contract->payment->processed_at?->format('Y-m-d H:i:s'),
                ] : null,
                'review' => $contract->review ? [
                    'id' => $contract->review->id,
                    'rating' => $contract->review->rating,
                    'comment' => $contract->review->comment,
                    'created_at' => $contract->review->created_at->format('Y-m-d H:i:s'),
                ] : null,
                'created_at' => $contract->created_at->format('Y-m-d H:i:s'),
            ];

            return response()->json([
                'success' => true,
                'data' => $contractData,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching contract', [
                'user_id' => $user->id,
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contract',
            ], 500);
        }
    }

    /**
     * Get contracts for a specific chat room
     */
    public function getContractsForChatRoom(Request $request, string $roomId): JsonResponse
    {
        $user = Auth::user();
        
        // Find the chat room and verify user has access
        $chatRoom = \App\Models\ChatRoom::where('room_id', $roomId)
            ->where(function ($query) use ($user) {
                $query->where('brand_id', $user->id)
                      ->orWhere('creator_id', $user->id);
            })
            ->first();

        if (!$chatRoom) {
            return response()->json([
                'success' => false,
                'message' => 'Chat room not found or access denied',
            ], 404);
        }

        try {
            // Get contracts for this chat room (through offers)
            $contracts = Contract::whereHas('offer', function ($query) use ($chatRoom) {
                $query->where('chat_room_id', $chatRoom->id);
            })
            ->with(['brand:id,name,avatar_url', 'creator:id,name,avatar_url', 'offer'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($contract) use ($user) {
                $otherUser = $user->isBrand() ? $contract->creator : $contract->brand;
                
                return [
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'description' => $contract->description,
                    'budget' => $contract->formatted_budget,
                    'creator_amount' => $contract->formatted_creator_amount,
                    'platform_fee' => $contract->formatted_platform_fee,
                    'estimated_days' => $contract->estimated_days,
                    'requirements' => $contract->requirements,
                    'status' => $contract->status,
                    'started_at' => $contract->started_at->format('Y-m-d H:i:s'),
                    'expected_completion_at' => $contract->expected_completion_at->format('Y-m-d H:i:s'),
                    'completed_at' => $contract->completed_at?->format('Y-m-d H:i:s'),
                    'cancelled_at' => $contract->cancelled_at?->format('Y-m-d H:i:s'),
                    'cancellation_reason' => $contract->cancellation_reason,
                    'days_until_completion' => $contract->days_until_completion,
                    'progress_percentage' => $contract->progress_percentage,
                    'is_overdue' => $contract->isOverdue(),
                    'is_near_completion' => $contract->is_near_completion,
                    'can_be_completed' => $contract->canBeCompleted(),
                    'can_be_cancelled' => $contract->canBeCancelled(),
                    'can_be_started' => $contract->canBeStarted(),
                    'has_brand_review' => $contract->has_brand_review,
                    'has_creator_review' => $contract->has_creator_review,
                    'has_both_reviews' => $contract->has_both_reviews,
                    'creator' => [
                        'id' => $contract->creator->id,
                        'name' => $contract->creator->name,
                        'avatar_url' => $contract->creator->avatar_url,
                    ],
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar_url' => $otherUser->avatar_url,
                    ],
                    'payment' => $contract->payment ? [
                        'id' => $contract->payment->id,
                        'status' => $contract->payment->status,
                        'total_amount' => $contract->payment->formatted_total_amount,
                        'creator_amount' => $contract->payment->formatted_creator_amount,
                        'platform_fee' => $contract->payment->formatted_platform_fee,
                        'processed_at' => $contract->payment->processed_at?->format('Y-m-d H:i:s'),
                    ] : null,
                    'review' => $contract->review ? [
                        'id' => $contract->review->id,
                        'rating' => $contract->review->rating,
                        'comment' => $contract->review->comment,
                        'created_at' => $contract->review->created_at->format('Y-m-d H:i:s'),
                    ] : null,
                    'created_at' => $contract->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $contracts,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching contracts for chat room', [
                'user_id' => $user->id,
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contracts',
            ], 500);
        }
    }

    /**
     * Activate a contract (change status from pending to active)
     */
    public function activate(int $id): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a brand
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can activate contracts',
            ], 403);
        }

        try {
            $contract = Contract::where('brand_id', $user->id)
                ->where('status', 'pending')
                ->find($id);

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato não encontrado ou não pode ser ativado',
                ], 404);
            }

            if (!$contract->canBeStarted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato não pode ser ativado',
                ], 400);
            }

            $contract->update([
                'status' => 'active',
                'workflow_status' => 'active',
                'started_at' => now(),
            ]);

            // Send automatic messages to both parties
            $this->sendApprovalMessages($contract);

            // Emit Socket.IO event for real-time updates
            $this->emitSocketEvent('contract_activated', [
                'roomId' => $contract->offer->chatRoom->room_id ?? null,
                'contractData' => [
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'description' => $contract->description,
                    'status' => $contract->status,
                    'workflow_status' => $contract->workflow_status,
                    'brand_id' => $contract->brand_id,
                    'creator_id' => $contract->creator_id,
                    'can_be_completed' => $contract->canBeCompleted(),
                    'can_be_cancelled' => $contract->canBeCancelled(),
                    'can_be_started' => $contract->canBeStarted(),
                    'budget' => $contract->formatted_budget,
                    'creator_amount' => $contract->formatted_creator_amount,
                    'platform_fee' => $contract->formatted_platform_fee,
                    'estimated_days' => $contract->estimated_days,
                    'started_at' => $contract->started_at?->format('Y-m-d H:i:s'),
                    'expected_completion_at' => $contract->expected_completion_at?->format('Y-m-d H:i:s'),
                    'days_until_completion' => $contract->days_until_completion,
                    'progress_percentage' => $contract->progress_percentage,
                    'is_overdue' => $contract->isOverdue(),
                    'is_near_completion' => $contract->is_near_completion,
                ],
                'senderId' => $user->id,
            ]);

            Log::info('Contract activated successfully', [
                'contract_id' => $contract->id,
                'brand_id' => $user->id,
                'creator_id' => $contract->creator_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contrato ativado com sucesso!',
                'data' => [
                    'contract_id' => $contract->id,
                    'status' => $contract->status,
                    'workflow_status' => $contract->workflow_status,
                    'next_step' => 'work_in_progress',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error activating contract', [
                'user_id' => $user->id,
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao ativar contrato. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Complete a contract (brand only)
     */
    public function complete(int $id): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a brand
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas marcas podem finalizar contratos',
            ], 403);
        }

        try {
            $contract = Contract::where('brand_id', $user->id)
                ->where('status', 'active')
                ->find($id);

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato não encontrado ou não pode ser finalizado',
                ], 404);
            }

            if (!$contract->canBeCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato não pode ser finalizado',
                ], 400);
            }

            if ($contract->complete()) {
                // Emit Socket.IO event for real-time updates
                $this->emitSocketEvent('contract_completed', [
                    'roomId' => $contract->offer->chatRoom->room_id ?? null,
                    'contractData' => [
                        'id' => $contract->id,
                        'title' => $contract->title,
                        'description' => $contract->description,
                        'status' => $contract->status,
                        'workflow_status' => $contract->workflow_status,
                        'brand_id' => $contract->brand_id,
                        'creator_id' => $contract->creator_id,
                        'can_be_completed' => $contract->canBeCompleted(),
                        'can_be_cancelled' => $contract->canBeCancelled(),
                        'can_be_started' => $contract->canBeStarted(),
                        'budget' => $contract->formatted_budget,
                        'creator_amount' => $contract->formatted_creator_amount,
                        'platform_fee' => $contract->formatted_platform_fee,
                        'estimated_days' => $contract->estimated_days,
                        'started_at' => $contract->started_at?->format('Y-m-d H:i:s'),
                        'expected_completion_at' => $contract->expected_completion_at?->format('Y-m-d H:i:s'),
                        'completed_at' => $contract->completed_at?->format('Y-m-d H:i:s'),
                        'days_until_completion' => $contract->days_until_completion,
                        'progress_percentage' => $contract->progress_percentage,
                        'is_overdue' => $contract->isOverdue(),
                        'is_near_completion' => $contract->is_near_completion,
                        'can_review' => true,
                    ],
                    'senderId' => $user->id,
                ]);

                            Log::info('Campaign completed successfully', [
                'contract_id' => $contract->id,
                'brand_id' => $user->id,
                'creator_id' => $contract->creator_id,
            ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Campanha finalizada com sucesso! Por favor, envie sua avaliação para liberar o pagamento para o criador.',
                    'data' => [
                        'contract_id' => $contract->id,
                        'status' => $contract->status,
                        'workflow_status' => $contract->workflow_status,
                        'requires_review' => true,
                        'next_step' => 'submit_review',
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Falha ao finalizar campanha',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error completing campaign', [
                'user_id' => $user->id,
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao finalizar campanha. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Cancel a contract
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validação falhou',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        try {
            $contract = Contract::where(function ($query) use ($user) {
                $query->where('brand_id', $user->id)
                      ->orWhere('creator_id', $user->id);
            })
            ->where('status', 'active')
            ->find($id);

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato não encontrado ou não pode ser cancelado',
                ], 404);
            }

            if (!$contract->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato não pode ser cancelado',
                ], 400);
            }

            if ($contract->cancel($request->reason)) {
                Log::info('Contract cancelled successfully', [
                    'contract_id' => $contract->id,
                    'user_id' => $user->id,
                    'reason' => $request->reason,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Contrato cancelado com sucesso',
                    'data' => [
                        'contract_id' => $contract->id,
                        'status' => $contract->status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Falha ao cancelar contrato',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error cancelling contract', [
                'user_id' => $user->id,
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao cancelar contrato. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Terminate a contract (brand only)
     */
    public function terminate(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validação falhou',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        // Check if user is a brand
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas marcas podem terminar contratos',
            ], 403);
        }

        try {
            $contract = Contract::where('brand_id', $user->id)
                ->where('status', 'active')
                ->find($id);

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato não encontrado ou não pode ser terminado',
                ], 404);
            }

            if (!$contract->canBeTerminated()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato não pode ser terminado',
                ], 400);
            }

            if ($contract->terminate($request->reason)) {
                // Emit Socket.IO event for real-time updates
                $this->emitSocketEvent('contract_terminated', [
                    'roomId' => $contract->offer->chatRoom->room_id ?? null,
                    'contractData' => [
                        'id' => $contract->id,
                        'title' => $contract->title,
                        'description' => $contract->description,
                        'status' => $contract->status,
                        'workflow_status' => $contract->workflow_status,
                        'brand_id' => $contract->brand_id,
                        'creator_id' => $contract->creator_id,
                        'cancelled_at' => $contract->cancelled_at?->format('Y-m-d H:i:s'),
                        'cancellation_reason' => $contract->cancellation_reason,
                    ],
                    'senderId' => $user->id,
                    'terminationReason' => $request->reason,
                ]);

                Log::info('Contract terminated successfully', [
                    'contract_id' => $contract->id,
                    'brand_id' => $user->id,
                    'reason' => $request->reason,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Contrato terminado com sucesso',
                    'data' => [
                        'contract_id' => $contract->id,
                        'status' => $contract->status,
                        'workflow_status' => $contract->workflow_status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Falha ao terminar contrato',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error terminating contract', [
                'user_id' => $user->id,
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao terminar contrato. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Dispute a contract
     */
    public function dispute(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validação falhou',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        try {
            $contract = Contract::where(function ($query) use ($user) {
                $query->where('brand_id', $user->id)
                      ->orWhere('creator_id', $user->id);
            })
            ->where('status', 'active')
            ->find($id);

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato não encontrado ou não pode ser disputado',
                ], 404);
            }

            if ($contract->dispute($request->reason)) {
                Log::info('Contract disputed successfully', [
                    'contract_id' => $contract->id,
                    'user_id' => $user->id,
                    'reason' => $request->reason,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Contrato disputado com sucesso. Nossa equipe revisará o caso.',
                    'data' => [
                        'contract_id' => $contract->id,
                        'status' => $contract->status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Falha ao disputar contrato',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error disputing contract', [
                'user_id' => $user->id,
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao disputar contrato. Tente novamente.',
            ], 500);
        }
    }
} 