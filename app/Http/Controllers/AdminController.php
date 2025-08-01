<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Get dashboard metrics
     */
    public function getDashboardMetrics(): JsonResponse
    {
        try {
            $metrics = [
                'pendingCampaignsCount' => Campaign::where('status', 'pending')->count(),
                'allActiveCampaignCount' => Campaign::where('is_active', true)->count(),
                'allRejectCampaignCount' => Campaign::where('status', 'rejected')->count(),
                'allUserCount' => User::whereNotIn('role', ['admin'])->count(), // Exclude admin users
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending campaigns for dashboard
     */
    public function getPendingCampaigns(): JsonResponse
    {
        try {
            $campaigns = Campaign::with('brand')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($campaign) {
                    return [
                        'id' => $campaign->id,
                        'title' => $campaign->title,
                        'brand' => $campaign->brand->company_name ?: $campaign->brand->name,
                        'type' => $campaign->campaign_type ?: 'Vídeo',
                        'value' => $campaign->budget ? (float) $campaign->budget : 0,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $campaigns
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending campaigns: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent users for dashboard
     */
    public function getRecentUsers(): JsonResponse
    {
        try {
            $users = User::whereNotIn('role', ['admin']) // Exclude admin users
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($user) {
                    $daysAgo = $user->created_at->diffInDays(now());
                    
                    // Map role to display name
                    $roleDisplay = match($user->role) {
                        'brand' => 'Marca',
                        'creator' => 'Criador',
                        default => 'Usuário'
                    };
                    
                    // Determine tag based on user role and premium status
                    $tag = match($user->role) {
                        'brand' => 'Marca',
                        'creator' => 'Criador',
                        default => 'Usuário'
                    };
                    
                    // If user has premium, show as "Pagante" (Paying)
                    if ($user->has_premium) {
                        $tag = 'Pagante';
                    }

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'role' => $roleDisplay,
                        'registeredDaysAgo' => $daysAgo,
                        'tag' => $tag,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get users by role with pagination and filtering
     */
    public function getUsers(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'nullable|in:creator,brand',
            'status' => 'nullable|in:active,blocked,removed,pending',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $role = $request->input('role');
        $status = $request->input('status');
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $query = User::query();

        // Filter by role
        if ($role) {
            $query->where('role', $role);
        }

        // Filter by status (account status)
        if ($status) {
            switch ($status) {
                case 'active':
                    $query->where('email_verified_at', '!=', null);
                    break;
                case 'blocked':
                    $query->where('email_verified_at', '=', null);
                    break;
                case 'removed':
                    $query->where('deleted_at', '!=', null);
                    break;
                case 'pending':
                    $query->where('email_verified_at', '=', null);
                    break;
            }
        }

        // Search functionality
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        // Get users with related data
        $users = $query->withCount([
            'campaignApplications as applied_campaigns',
            'campaignApplications as approved_campaigns' => function ($q) {
                $q->where('status', 'approved');
            },
            'campaigns as created_campaigns'
        ])
        ->orderBy('created_at', 'desc')
        ->paginate($perPage, ['*'], 'page', $page);

        // Transform the data to match frontend expectations
        $transformedUsers = $users->getCollection()->map(function ($user) {
            return $this->transformUserData($user);
        });

        return response()->json([
            'success' => true,
            'data' => $transformedUsers,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        ]);
    }

    /**
     * Get creators with enhanced data
     */
    public function getCreators(Request $request): JsonResponse
    {
        $request->merge(['role' => 'creator']);
        return $this->getUsers($request);
    }

    /**
     * Get brands with enhanced data
     */
    public function getBrands(Request $request): JsonResponse
    {
        $request->merge(['role' => 'brand']);
        return $this->getUsers($request);
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'creators' => User::where('role', 'creator')->count(),
            'brands' => User::where('role', 'brand')->count(),
            'premium_users' => User::where('has_premium', true)->count(),
            'verified_students' => User::where('student_verified', true)->count(),
            'active_users' => User::where('email_verified_at', '!=', null)->count(),
            'pending_users' => User::where('email_verified_at', '=', null)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Update user status (activate, block, remove)
     */
    public function updateUserStatus(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:activate,block,remove',
        ]);

        $action = $request->input('action');

        try {
            switch ($action) {
                case 'activate':
                    $user->update([
                        'email_verified_at' => now(),
                    ]);
                    $message = 'User activated successfully';
                    break;

                case 'block':
                    $user->update([
                        'email_verified_at' => null,
                    ]);
                    $message = 'User blocked successfully';
                    break;

                case 'remove':
                    $user->delete();
                    $message = 'User removed successfully';
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid action'
                    ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'user' => $this->transformUserData($user->fresh())
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transform user data for admin interface
     */
    private function transformUserData(User $user): array
    {
        $isCreator = $user->role === 'creator';
        
        if ($isCreator) {
            // For creators, show their actual role from database
            $status = 'Criador'; // Default for creator role
            $statusColor = 'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-200';
            
            // If they have premium, show as "Pagante" (Paying)
            if ($user->has_premium) {
                $status = 'Pagante';
                $statusColor = 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-200';
            }
            
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $status,
                'statusColor' => $statusColor,
                'time' => $this->getUserTimeStatus($user),
                'campaigns' => $user->applied_campaigns . ' aplicadas / ' . $user->approved_campaigns . ' aprovadas',
                'accountStatus' => $this->getAccountStatus($user),
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'has_premium' => $user->has_premium,
                'student_verified' => $user->student_verified,
                'premium_expires_at' => $user->premium_expires_at,
                'free_trial_expires_at' => $user->free_trial_expires_at,
            ];
        } else {
            // For brands, show their actual role from database
            $status = 'Marca'; // Default for brand role
            $statusColor = 'bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-200';
            
            // If they have premium, show as "Pagante" (Paying)
            if ($user->has_premium) {
                $status = 'Pagante';
                $statusColor = 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-200';
            }
            
            return [
                'id' => $user->id,
                'company' => $user->company_name ?: $user->name,
                'brandName' => $user->company_name ?: $user->name,
                'email' => $user->email,
                'status' => $status,
                'statusColor' => $statusColor,
                'campaigns' => $user->created_campaigns,
                'accountStatus' => $this->getAccountStatus($user),
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'has_premium' => $user->has_premium,
                'premium_expires_at' => $user->premium_expires_at,
                'free_trial_expires_at' => $user->free_trial_expires_at,
            ];
        }
    }

    /**
     * Get user time status
     */
    private function getUserTimeStatus(User $user): string
    {
        if ($user->has_premium && $user->premium_expires_at === null) {
            return 'Ilimitado';
        }

        if ($user->has_premium && $user->premium_expires_at) {
            $months = $user->premium_expires_at->diffInMonths(now());
            return $months . ' meses';
        }

        if ($user->free_trial_expires_at) {
            $months = $user->free_trial_expires_at->diffInMonths(now());
            return $months . ' meses';
        }

        $months = $user->created_at->diffInMonths(now());
        return $months . ' meses';
    }

    /**
     * Get account status
     */
    private function getAccountStatus(User $user): string
    {
        if ($user->deleted_at) {
            return 'Removido';
        }

        if ($user->email_verified_at) {
            return 'Ativo';
        }

        // Check if user has been inactive for too long
        if ($user->created_at->diffInDays(now()) > 30) {
            return 'Bloqueado';
        }

        return 'Pendente';
    }
} 