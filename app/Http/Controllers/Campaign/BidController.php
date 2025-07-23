<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreBidRequest;
use App\Models\Campaign;
use App\Models\Bid;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BidController extends Controller
{
    /**
     * Display a listing of bids.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Bid::with(['campaign', 'user']);

        // Apply filters based on user role
        if ($user->isCreator()) {
            // Creators see only their own bids
            $query->where('user_id', $user->id);
        } elseif ($user->isBrand()) {
            // Brands see only bids on their campaigns
            $query->whereHas('campaign', function($q) use ($user) {
                $q->where('brand_id', $user->id);
            });
        } elseif ($user->isAdmin()) {
            // Admin sees all bids
            // No additional filters needed
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Apply additional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }

        if ($request->has('bid_amount_min')) {
            $query->where('bid_amount', '>=', $request->bid_amount_min);
        }

        if ($request->has('bid_amount_max')) {
            $query->where('bid_amount', '<=', $request->bid_amount_max);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $bids = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $bids,
            'message' => 'Bids retrieved successfully'
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a new bid on a campaign.
     */
    public function store(StoreBidRequest $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validated();
        $validated['campaign_id'] = $campaign->id;
        $validated['user_id'] = auth()->id();

        $bid = Bid::create($validated);

        // Notify admin of new bid
        \App\Services\NotificationService::notifyAdminOfNewBid($bid);

        return response()->json([
            'success' => true,
            'data' => $bid->load(['campaign', 'user']),
            'message' => 'Bid submitted successfully'
        ], 201);
    }

    /**
     * Display the specified bid.
     */
    public function show(Bid $bid): JsonResponse
    {
        $user = auth()->user();

        // Check authorization
        if ($user->isCreator() && $bid->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($user->isBrand() && $bid->campaign->brand_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $bid->load(['campaign', 'user']);

        return response()->json([
            'success' => true,
            'data' => $bid,
            'message' => 'Bid retrieved successfully'
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified bid.
     */
    public function update(Request $request, Bid $bid): JsonResponse
    {
        $user = auth()->user();

        // Only bid owner can update their bids
        if (!$user->isCreator() || $bid->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Can't update non-pending bids
        if (!$bid->isPending()) {
            return response()->json(['error' => 'Only pending bids can be updated'], 400);
        }

        // Check if campaign still accepts bids
        if (!$bid->campaign->canReceiveBids()) {
            return response()->json(['error' => 'Campaign no longer accepts bids'], 400);
        }

        $validated = $request->validate([
            'bid_amount' => ['sometimes', 'numeric', 'min:1', 'max:999999.99'],
            'proposal' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'portfolio_links' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'estimated_delivery_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $bid->update($validated);

        return response()->json([
            'success' => true,
            'data' => $bid->load(['campaign', 'user']),
            'message' => 'Bid updated successfully'
        ]);
    }

    /**
     * Remove the specified bid.
     */
    public function destroy(Bid $bid): JsonResponse
    {
        $user = auth()->user();

        // Only bid owner can delete their bids
        if (!$user->isCreator() || $bid->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Can't delete non-pending bids
        if (!$bid->isPending()) {
            return response()->json(['error' => 'Only pending bids can be deleted'], 400);
        }

        $bid->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bid deleted successfully'
        ]);
    }

    /**
     * Accept a bid (Brand only).
     */
    public function accept(Bid $bid): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isBrand() || $bid->campaign->brand_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$bid->isPending()) {
            return response()->json(['error' => 'Only pending bids can be accepted'], 400);
        }

        if ($bid->campaign->hasAcceptedBid()) {
            return response()->json(['error' => 'Campaign already has an accepted bid'], 400);
        }

        $bid->accept();

        // Notify admin of bid acceptance
        \App\Services\NotificationService::notifyAdminOfSystemActivity('bid_accepted', [
            'bid_id' => $bid->id,
            'campaign_id' => $bid->campaign_id,
            'campaign_title' => $bid->campaign->title,
            'creator_name' => $bid->user->name,
            'brand_name' => $bid->campaign->brand->name,
            'bid_amount' => $bid->bid_amount,
            'accepted_by' => $user->name,
        ]);

        return response()->json([
            'success' => true,
            'data' => $bid->load(['campaign', 'user']),
            'message' => 'Bid accepted successfully'
        ]);
    }

    /**
     * Reject a bid (Brand only).
     */
    public function reject(Request $request, Bid $bid): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isBrand() || $bid->campaign->brand_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$bid->isPending()) {
            return response()->json(['error' => 'Only pending bids can be rejected'], 400);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000']
        ]);

        $bid->reject($validated['reason'] ?? null);

        // Notify admin of bid rejection
        \App\Services\NotificationService::notifyAdminOfSystemActivity('bid_rejected', [
            'bid_id' => $bid->id,
            'campaign_id' => $bid->campaign_id,
            'campaign_title' => $bid->campaign->title,
            'creator_name' => $bid->user->name,
            'brand_name' => $bid->campaign->brand->name,
            'bid_amount' => $bid->bid_amount,
            'rejected_by' => $user->name,
            'rejection_reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $bid->load(['campaign', 'user']),
            'message' => 'Bid rejected successfully'
        ]);
    }

    /**
     * Withdraw a bid (Creator only).
     */
    public function withdraw(Bid $bid): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isCreator() || $bid->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$bid->isPending()) {
            return response()->json(['error' => 'Only pending bids can be withdrawn'], 400);
        }

        $bid->withdraw();

        // Notify admin of bid withdrawal
        \App\Services\NotificationService::notifyAdminOfSystemActivity('bid_withdrawn', [
            'bid_id' => $bid->id,
            'campaign_id' => $bid->campaign_id,
            'campaign_title' => $bid->campaign->title,
            'creator_name' => $bid->user->name,
            'brand_name' => $bid->campaign->brand->name,
            'bid_amount' => $bid->bid_amount,
            'withdrawn_by' => $user->name,
        ]);

        return response()->json([
            'success' => true,
            'data' => $bid->load(['campaign', 'user']),
            'message' => 'Bid withdrawn successfully'
        ]);
    }

    /**
     * Get bids for a specific campaign.
     */
    public function campaignBids(Request $request, Campaign $campaign): JsonResponse
    {
        $user = auth()->user();

        // Check authorization
        if ($user->isBrand() && $campaign->brand_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($user->isCreator() && !$campaign->isApproved()) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        $query = $campaign->bids()->with(['user']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('bid_amount_min')) {
            $query->where('bid_amount', '>=', $request->bid_amount_min);
        }

        if ($request->has('bid_amount_max')) {
            $query->where('bid_amount', '<=', $request->bid_amount_max);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'bid_amount');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $bids = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $bids,
            'message' => 'Campaign bids retrieved successfully'
        ]);
    }
}
