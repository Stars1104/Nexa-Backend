<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\ChatRoom;
use App\Models\User;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class OfferController extends Controller
{
    /**
     * Create a new offer
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'creator_id' => 'required|integer|exists:users,id',
            'chat_room_id' => 'required|string',
            'budget' => 'required|numeric|min:10|max:100000',
            'estimated_days' => 'required|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        
        // Check if user is a brand
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can create offers',
            ], 403);
        }

        // Check if creator exists and is a creator
        $creator = User::find($request->creator_id);
        if (!$creator || !$creator->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid creator',
            ], 404);
        }

        // Check if chat room exists and user is part of it
        $chatRoom = ChatRoom::where('room_id', $request->chat_room_id)
            ->where('brand_id', $user->id)
            ->where('creator_id', $creator->id)
            ->first();

        if (!$chatRoom) {
            return response()->json([
                'success' => false,
                'message' => 'Chat room not found or access denied',
            ], 404);
        }

        // Check if there's already a pending offer
        $existingOffer = Offer::where('brand_id', $user->id)
            ->where('creator_id', $creator->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now()) // Only check for non-expired offers
            ->first();

        if ($existingOffer) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending offer for this creator. Please wait for them to respond or cancel the existing offer.',
                'existing_offer_id' => $existingOffer->id,
            ], 400);
        }

        try {
            $offer = Offer::create([
                'brand_id' => $user->id,
                'creator_id' => $creator->id,
                'chat_room_id' => $chatRoom->id,
                'title' => 'Oferta de Projeto', // Default title
                'description' => 'Oferta enviada via chat', // Default description
                'budget' => $request->budget,
                'estimated_days' => $request->estimated_days,
                'requirements' => [], // Empty requirements array
                'expires_at' => now()->addDays(1), // Changed from 3 to 1 day
            ]);

            // Notify creator about new offer
            NotificationService::notifyUserOfNewOffer($offer);

            // Create chat message for the offer
            $this->createOfferChatMessage($chatRoom, 'offer_created', [
                'sender_id' => $user->id,
                'message' => "Oferta enviada: {$offer->formatted_budget}",
                'offer_data' => [
                    'offer_id' => $offer->id,
                    'title' => $offer->title,
                    'description' => $offer->description,
                    'budget' => $offer->budget,
                    'formatted_budget' => $offer->formatted_budget,
                    'estimated_days' => $offer->estimated_days,
                    'status' => 'pending', // Explicitly set status to pending
                    'expires_at' => $offer->expires_at->format('Y-m-d H:i:s'),
                    'days_until_expiry' => $offer->days_until_expiry,
                    'sender' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'avatar_url' => $user->avatar_url,
                    ],
                ],
            ]);

            Log::info('Offer created successfully', [
                'offer_id' => $offer->id,
                'brand_id' => $user->id,
                'creator_id' => $creator->id,
                'budget' => $request->budget,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Offer sent successfully',
                'data' => [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'budget' => $offer->formatted_budget,
                    'estimated_days' => $offer->estimated_days,
                    'expires_at' => $offer->expires_at->format('Y-m-d H:i:s'),
                    'days_until_expiry' => $offer->days_until_expiry,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating offer', [
                'user_id' => $user->id,
                'creator_id' => $creator->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create offer. Please try again.',
            ], 500);
        }
    }

    /**
     * Create a chat message for offer events
     */
    private function createOfferChatMessage(ChatRoom $chatRoom, string $messageType, array $data = []): void
    {
        try {
            $messageData = [
                'chat_room_id' => $chatRoom->id,
                'sender_id' => $data['sender_id'] ?? 0, // System message
                'message' => $data['message'] ?? '',
                'message_type' => 'offer',
                'offer_data' => json_encode($data['offer_data'] ?? []),
            ];

            Message::create($messageData);
        } catch (\Exception $e) {
            Log::error('Failed to create offer chat message', [
                'chat_room_id' => $chatRoom->id,
                'message_type' => $messageType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get offers for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $type = $request->get('type', 'received'); // 'sent' or 'received'
        $status = $request->get('status'); // 'pending', 'accepted', 'rejected', 'expired'

        try {
            $query = $user->isBrand() 
                ? $user->sentOffers() 
                : $user->receivedOffers();

            if ($status) {
                $query->where('status', $status);
            }

            $offers = $query->with(['brand:id,name,avatar_url', 'creator:id,name,avatar_url'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            $offers->getCollection()->transform(function ($offer) use ($user) {
                $otherUser = $user->isBrand() ? $offer->creator : $offer->brand;
                
                return [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'description' => $offer->description,
                    'budget' => $offer->formatted_budget,
                    'estimated_days' => $offer->estimated_days,
                    'requirements' => $offer->requirements,
                    'status' => $offer->status,
                    'expires_at' => $offer->expires_at->format('Y-m-d H:i:s'),
                    'days_until_expiry' => $offer->days_until_expiry,
                    'is_expiring_soon' => $offer->is_expiring_soon,
                    'accepted_at' => $offer->accepted_at?->format('Y-m-d H:i:s'),
                    'rejected_at' => $offer->rejected_at?->format('Y-m-d H:i:s'),
                    'rejection_reason' => $offer->rejection_reason,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar_url' => $otherUser->avatar_url,
                    ],
                    'created_at' => $offer->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $offers,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching offers', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch offers',
            ], 500);
        }
    }

    /**
     * Get a specific offer
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $offer = Offer::with(['brand:id,name,avatar_url', 'creator:id,name,avatar_url'])
                ->where(function ($query) use ($user) {
                    $query->where('brand_id', $user->id)
                          ->orWhere('creator_id', $user->id);
                })
                ->find($id);

            if (!$offer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Offer not found',
                ], 404);
            }

            $otherUser = $user->isBrand() ? $offer->creator : $offer->brand;

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'description' => $offer->description,
                    'budget' => $offer->formatted_budget,
                    'estimated_days' => $offer->estimated_days,
                    'requirements' => $offer->requirements,
                    'status' => $offer->status,
                    'expires_at' => $offer->expires_at->format('Y-m-d H:i:s'),
                    'days_until_expiry' => $offer->days_until_expiry,
                    'is_expiring_soon' => $offer->is_expiring_soon,
                    'can_be_accepted' => $offer->canBeAccepted(),
                    'accepted_at' => $offer->accepted_at?->format('Y-m-d H:i:s'),
                    'rejected_at' => $offer->rejected_at?->format('Y-m-d H:i:s'),
                    'rejection_reason' => $offer->rejection_reason,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar_url' => $otherUser->avatar_url,
                    ],
                    'created_at' => $offer->created_at->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching offer', [
                'user_id' => $user->id,
                'offer_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch offer',
            ], 500);
        }
    }

    /**
     * Accept an offer
     */
    public function accept(int $id): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can accept offers',
            ], 403);
        }

        try {
            $offer = Offer::where('creator_id', $user->id)
                ->where('status', 'pending')
                ->find($id);

            if (!$offer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Offer not found or cannot be accepted',
                ], 404);
            }

            if (!$offer->canBeAccepted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Offer cannot be accepted (expired or already processed)',
                ], 400);
            }

            if ($offer->accept()) {
                // Get the chat room
                $chatRoom = ChatRoom::find($offer->chat_room_id);
                
                if ($chatRoom) {
                    // Get the created contract
                    $contract = $offer->contract;
                    
                    // Create chat message for offer acceptance
                    $this->createOfferChatMessage($chatRoom, 'offer_accepted', [
                        'sender_id' => $user->id,
                        'message' => "Oferta aceita! Contrato criado.",
                        'offer_data' => [
                            'offer_id' => $offer->id,
                            'title' => $offer->title,
                            'description' => $offer->description,
                            'budget' => $offer->budget,
                            'formatted_budget' => $offer->formatted_budget,
                            'estimated_days' => $offer->estimated_days,
                            'status' => $offer->status,
                            'contract_id' => $contract->id ?? null,
                            'contract_status' => $contract->status ?? null,
                            'can_be_completed' => $contract->canBeCompleted(),
                            'sender' => [
                                'id' => $user->id,
                                'name' => $user->name,
                                'avatar_url' => $user->avatar_url,
                            ],
                        ],
                    ]);
                }

                Log::info('Offer accepted successfully', [
                    'offer_id' => $offer->id,
                    'creator_id' => $user->id,
                    'brand_id' => $offer->brand_id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Offer accepted successfully! Contract has been created.',
                    'data' => [
                        'offer_id' => $offer->id,
                        'contract_id' => $offer->contract->id,
                        'status' => $offer->status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to accept offer',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error accepting offer', [
                'user_id' => $user->id,
                'offer_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to accept offer. Please try again.',
            ], 500);
        }
    }

    /**
     * Reject an offer
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        // Check if user is a creator
        if (!$user->isCreator()) {
            return response()->json([
                'success' => false,
                'message' => 'Only creators can reject offers',
            ], 403);
        }

        try {
            $offer = Offer::where('creator_id', $user->id)
                ->where('status', 'pending')
                ->find($id);

            if (!$offer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Offer not found or cannot be rejected',
                ], 404);
            }

            if (!$offer->canBeAccepted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Offer cannot be rejected (expired or already processed)',
                ], 400);
            }

            if ($offer->reject($request->reason)) {
                // Get the chat room
                $chatRoom = ChatRoom::find($offer->chat_room_id);
                
                if ($chatRoom) {
                    // Create chat message for offer rejection
                    $this->createOfferChatMessage($chatRoom, 'offer_rejected', [
                        'sender_id' => $user->id,
                        'message' => "Oferta rejeitada" . ($request->reason ? ": {$request->reason}" : ""),
                        'offer_data' => [
                            'offer_id' => $offer->id,
                            'title' => $offer->title,
                            'description' => $offer->description,
                            'budget' => $offer->budget,
                            'formatted_budget' => $offer->formatted_budget,
                            'estimated_days' => $offer->estimated_days,
                            'status' => $offer->status,
                            'rejection_reason' => $request->reason,
                            'sender' => [
                                'id' => $user->id,
                                'name' => $user->name,
                                'avatar_url' => $user->avatar_url,
                            ],
                        ],
                    ]);
                }

                Log::info('Offer rejected successfully', [
                    'offer_id' => $offer->id,
                    'creator_id' => $user->id,
                    'brand_id' => $offer->brand_id,
                    'reason' => $request->reason,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Offer rejected successfully',
                    'data' => [
                        'offer_id' => $offer->id,
                        'status' => $offer->status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject offer',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error rejecting offer', [
                'user_id' => $user->id,
                'offer_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject offer. Please try again.',
            ], 500);
        }
    }

    /**
     * Cancel an offer (brand only)
     */
    public function cancel(int $id): JsonResponse
    {
        $user = Auth::user();

        // Check if user is a brand
        if (!$user->isBrand()) {
            return response()->json([
                'success' => false,
                'message' => 'Only brands can cancel offers',
            ], 403);
        }

        try {
            $offer = Offer::where('brand_id', $user->id)
                ->where('status', 'pending')
                ->find($id);

            if (!$offer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Offer not found or cannot be cancelled',
                ], 404);
            }

            $offer->update([
                'status' => 'cancelled',
            ]);

            // Get the chat room
            $chatRoom = ChatRoom::find($offer->chat_room_id);
            
            if ($chatRoom) {
                // Create chat message for offer cancellation
                $this->createOfferChatMessage($chatRoom, 'offer_cancelled', [
                    'sender_id' => $user->id,
                    'message' => "Oferta cancelada",
                    'offer_data' => [
                        'offer_id' => $offer->id,
                        'title' => $offer->title,
                        'description' => $offer->description,
                        'budget' => $offer->budget,
                        'formatted_budget' => $offer->formatted_budget,
                        'estimated_days' => $offer->estimated_days,
                        'status' => $offer->status,
                        'sender' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'avatar_url' => $user->avatar_url,
                        ],
                    ],
                ]);
            }

            // Notify creator about cancelled offer
            NotificationService::notifyUserOfOfferCancelled($offer);

            Log::info('Offer cancelled successfully', [
                'offer_id' => $offer->id,
                'brand_id' => $user->id,
                'creator_id' => $offer->creator_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Offer cancelled successfully',
                'data' => [
                    'offer_id' => $offer->id,
                    'status' => $offer->status,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error cancelling offer', [
                'user_id' => $user->id,
                'offer_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel offer. Please try again.',
            ], 500);
        }
    }

    /**
     * Get offers for a specific chat room
     */
    public function getOffersForChatRoom(Request $request, string $roomId): JsonResponse
    {
        $user = Auth::user();
        
        // Find the chat room and verify user has access
        $chatRoom = ChatRoom::where('room_id', $roomId)
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

        // Get offers for this chat room
        $offers = Offer::where('chat_room_id', $chatRoom->id)
            ->with(['brand', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($offer) use ($user) {
                return [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'description' => $offer->description,
                    'budget' => $offer->budget,
                    'formatted_budget' => $offer->formatted_budget,
                    'estimated_days' => $offer->estimated_days,
                    'requirements' => $offer->requirements,
                    'status' => $offer->status,
                    'expires_at' => $offer->expires_at?->format('Y-m-d H:i:s'),
                    'days_until_expiry' => $offer->days_until_expiry,
                    'accepted_at' => $offer->accepted_at?->format('Y-m-d H:i:s'),
                    'rejected_at' => $offer->rejected_at?->format('Y-m-d H:i:s'),
                    'rejection_reason' => $offer->rejection_reason,
                    'brand' => [
                        'id' => $offer->brand->id,
                        'name' => $offer->brand->name,
                        'avatar' => $offer->brand->avatar_url,
                    ],
                    'creator' => [
                        'id' => $offer->creator->id,
                        'name' => $offer->creator->name,
                        'avatar' => $offer->creator->avatar_url,
                    ],
                    'can_accept' => $offer->canBeAccepted(),
                    'can_reject' => $offer->canBeRejected(),
                    'can_cancel' => $offer->canBeCancelled(),
                    'is_expired' => $offer->isExpired(),
                    'created_at' => $offer->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $offers,
        ]);
    }
} 