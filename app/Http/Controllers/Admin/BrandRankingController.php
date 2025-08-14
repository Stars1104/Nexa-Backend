<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Campaign;
use App\Models\Contract;
use App\Models\JobPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BrandRankingController extends Controller
{
    /**
     * Get brand rankings based on different metrics
     */
    public function getBrandRankings(): JsonResponse
    {
        try {
            $rankings = [
                'mostPosted' => $this->getMostPostedBrands(),
                'mostHired' => $this->getMostHiredBrands(),
                'mostPaid' => $this->getMostPaidBrands(),
            ];

            return response()->json([
                'success' => true,
                'data' => $rankings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch brand rankings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get brands ranked by most campaigns posted
     */
    private function getMostPostedBrands(): array
    {
        return User::where('role', 'brand')
            ->withCount(['campaigns as total_campaigns'])
            ->having('total_campaigns', '>', 0)
            ->orderBy('total_campaigns', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($brand, $index) {
                return [
                    'rank' => $index + 1,
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'company_name' => $brand->company_name,
                    'display_name' => $brand->company_name ?: $brand->name,
                    'total_campaigns' => $brand->total_campaigns,
                    'avatar_url' => $brand->avatar_url,
                    'has_premium' => $brand->has_premium,
                    'created_at' => $brand->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get brands ranked by most creators hired (completed contracts)
     */
    private function getMostHiredBrands(): array
    {
        return User::where('role', 'brand')
            ->withCount([
                'brandContracts as total_contracts' => function ($query) {
                    $query->where('status', 'completed');
                }
            ])
            ->having('total_contracts', '>', 0)
            ->orderBy('total_contracts', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($brand, $index) {
                return [
                    'rank' => $index + 1,
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'company_name' => $brand->company_name,
                    'display_name' => $brand->company_name ?: $brand->name,
                    'total_contracts' => $brand->total_contracts,
                    'avatar_url' => $brand->avatar_url,
                    'has_premium' => $brand->has_premium,
                    'created_at' => $brand->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get brands ranked by highest total payments
     */
    private function getMostPaidBrands(): array
    {
        return User::where('role', 'brand')
            ->withSum(['brandPayments as total_paid' => function ($query) {
                $query->where('status', 'completed');
            }], 'total_amount')
            ->having('total_paid', '>', 0)
            ->orderBy('total_paid', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($brand, $index) {
                return [
                    'rank' => $index + 1,
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'company_name' => $brand->company_name,
                    'display_name' => $brand->company_name ?: $brand->name,
                    'total_paid' => (float) $brand->total_paid,
                    'total_paid_formatted' => 'R$ ' . number_format($brand->total_paid, 2, ',', '.'),
                    'avatar_url' => $brand->avatar_url,
                    'has_premium' => $brand->has_premium,
                    'created_at' => $brand->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get comprehensive brand ranking with all metrics
     */
    public function getComprehensiveRankings(): JsonResponse
    {
        try {
            $brands = User::where('role', 'brand')
                ->withCount([
                    'campaigns as total_campaigns',
                    'brandContracts as total_contracts' => function ($query) {
                        $query->where('status', 'completed');
                    }
                ])
                ->withSum(['brandPayments as total_paid' => function ($query) {
                    $query->where('status', 'completed');
                }], 'total_amount')
                ->orderBy('total_paid', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($brand, $index) {
                    return [
                        'rank' => $index + 1,
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'company_name' => $brand->company_name,
                        'display_name' => $brand->company_name ?: $brand->name,
                        'total_campaigns' => $brand->total_campaigns,
                        'total_contracts' => $brand->total_contracts,
                        'total_paid' => (float) ($brand->total_paid ?? 0),
                        'total_paid_formatted' => 'R$ ' . number_format($brand->total_paid ?? 0, 2, ',', '.'),
                        'avatar_url' => $brand->avatar_url,
                        'has_premium' => $brand->has_premium,
                        'created_at' => $brand->created_at,
                        'score' => $this->calculateRankingScore($brand->total_campaigns, $brand->total_contracts, $brand->total_paid ?? 0),
                    ];
                })
                ->sortByDesc('score')
                ->values()
                ->map(function ($brand, $index) {
                    $brand['rank'] = $index + 1;
                    return $brand;
                })
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $brands,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comprehensive brand rankings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate a composite ranking score
     */
    private function calculateRankingScore(int $campaigns, int $contracts, float $payments): float
    {
        // Normalize values and create a weighted score
        $campaignScore = min($campaigns / 10, 1) * 30; // Max 30 points
        $contractScore = min($contracts / 20, 1) * 30; // Max 30 points
        $paymentScore = min($payments / 10000, 1) * 40; // Max 40 points (R$ 10k = 100%)

        return $campaignScore + $contractScore + $paymentScore;
    }
} 