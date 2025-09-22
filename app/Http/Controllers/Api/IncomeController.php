<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncomeRecord;
use App\Models\Wallet;
use App\Models\LedgerTransaction;
use App\Models\ClubEntry;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\UserTree;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;

class IncomeController extends Controller
{
    use ApiResponse;

    /**
     * Get user's income summary
     * GET /api/user/income/summary
     */
    public function summary(Request $request)
    {
        $user = JWTAuth::user();

        // Get income statistics
        $incomeStats = IncomeRecord::getUserIncomeStats($user->id);

        // Get wallet balances
        $wallets = Wallet::where('user_id', $user->id)->get();
        $walletBalances = [];
        foreach ($wallets as $wallet) {
            $walletBalances[$wallet->wallet_type] = [
                'balance' => $wallet->balance,
                'pending_balance' => $wallet->pending_balance,
                'currency' => $wallet->currency
            ];
        }

        // Get total wallet balance
        $totalBalance = $wallets->sum('balance');
        $totalPending = $wallets->sum('pending_balance');

        $summary = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'income_summary' => $incomeStats,
            'wallet_summary' => [
                'total_balance' => $totalBalance,
                'total_pending' => $totalPending,
                'wallets' => $walletBalances
            ],
            'income_types' => [
                'level' => 'Level Income - Fixed amount per upline level',
                'fasttrack' => 'Fasttrack Income - Percentage from direct referrals',
                'club' => 'Club Income - Matrix expansion rewards',
                'company_allocation' => 'Company Allocation - Pool distribution'
            ]
        ];

        return $this->success($summary, 'Income summary retrieved successfully');
    }

    /**
     * Get user's income records by type
     * GET /api/user/income/records?type=level&status=paid&per_page=20
     */
    public function records(Request $request)
    {
        $user = JWTAuth::user();

        $query = IncomeRecord::where('user_id', $user->id)
            ->with(['originUser', 'userPackage.package', 'incomeConfig']);

        // Filter by income type
        if ($request->filled('type')) {
            $query->where('income_type', $request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by currency
        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        // Date range filters
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Sort by created_at desc by default
        $query->orderBy('created_at', 'desc');

        $records = $query->paginate($request->get('per_page', 20));

        // Transform the data for better API response
        $transformedRecords = $records->getCollection()->map(function ($record) {
            return [
                'id' => $record->id,
                'income_type' => $record->income_type,
                'amount' => $record->amount,
                'currency' => $record->currency,
                'status' => $record->status,
                'description' => $this->getIncomeDescription($record),
                'origin_user' => $record->originUser ? [
                    'id' => $record->originUser->id,
                    'name' => $record->originUser->name
                ] : null,
                'package' => $record->userPackage && $record->userPackage->package ? [
                    'id' => $record->userPackage->package->id,
                    'name' => $record->userPackage->package->name,
                    'level' => $record->userPackage->package->level_number
                ] : null,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at
            ];
        });

        $records->setCollection($transformedRecords);

        return $this->paginated($records, 'Income records retrieved successfully');
    }

    /**
     * Get user's income by type
     * GET /api/user/income/by-type
     */
    public function byType(Request $request)
    {
        $user = JWTAuth::user();

        $incomeTypes = ['level', 'fasttrack', 'club', 'company_allocation'];
        $results = [];

        foreach ($incomeTypes as $type) {
            $totalPaid = IncomeRecord::getTotalIncomeByType($user->id, $type);
            $totalPending = IncomeRecord::forUser($user->id)
                ->byType($type)
                ->pending()
                ->sum('amount');

            $results[$type] = [
                'type' => $type,
                'total_paid' => $totalPaid,
                'total_pending' => $totalPending,
                'total_earned' => $totalPaid + $totalPending,
                'description' => $this->getIncomeTypeDescription($type)
            ];
        }

        return $this->success($results, 'Income by type retrieved successfully');
    }

    /**
     * Get user's wallet details
     * GET /api/user/wallets
     */
    public function wallets(Request $request)
    {
        $user = JWTAuth::user();

        $wallets = Wallet::where('user_id', $user->id)
            ->orderBy('wallet_type')
            ->get();

        $walletData = $wallets->map(function ($wallet) {
            return [
                'id' => $wallet->id,
                'wallet_type' => $wallet->wallet_type,
                'balance' => $wallet->balance,
                'pending_balance' => $wallet->pending_balance,
                'currency' => $wallet->currency,
                'description' => $this->getWalletDescription($wallet->wallet_type),
                'created_at' => $wallet->created_at,
                'updated_at' => $wallet->updated_at
            ];
        });

        return $this->success($walletData, 'Wallet details retrieved successfully');
    }

    /**
     * Get user's club matrix information
     * GET /api/user/club/matrix
     */
    public function clubMatrix(Request $request)
    {
        $user = JWTAuth::user();

        // Get club entries where user is the sponsor
        $clubEntries = ClubEntry::where('sponsor_id', $user->id)
            ->with(['user'])
            ->orderBy('level')
            ->orderBy('created_at')
            ->get();

        // Group by level
        $matrixByLevel = [];
        foreach ($clubEntries as $entry) {
            $level = $entry->level;
            if (!isset($matrixByLevel[$level])) {
                $matrixByLevel[$level] = [];
            }
            $matrixByLevel[$level][] = [
                'id' => $entry->id,
                'user' => [
                    'id' => $entry->user->id,
                    'name' => $entry->user->name,
                    'email' => $entry->user->email
                ],
                'status' => $entry->status,
                'created_at' => $entry->created_at
            ];
        }

        // Get club income earned
        $clubIncome = IncomeRecord::where('user_id', $user->id)
            ->where('income_type', 'club')
            ->sum('amount');

        $matrixData = [
            'sponsor' => [
                'id' => $user->id,
                'name' => $user->name
            ],
            'total_club_income' => $clubIncome,
            'matrix_levels' => $matrixByLevel,
            'total_members' => $clubEntries->count(),
            'levels_filled' => count($matrixByLevel)
        ];

        return $this->success($matrixData, 'Club matrix retrieved successfully');
    }

    /**
     * Get user's ledger transactions
     * GET /api/user/ledger/transactions
     */
    public function ledgerTransactions(Request $request)
    {
        $user = JWTAuth::user();

        $query = LedgerTransaction::where(function ($q) use ($user) {
            $q->where('user_from', $user->id)
                ->orWhere('user_to', $user->id);
        })->with(['userFrom', 'userTo', 'walletFrom', 'walletTo']);

        // Filter by transaction type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // Transform the data
        $transformedTransactions = $transactions->getCollection()->map(function ($transaction) use ($user) {
            $isIncoming = $transaction->user_to == $user->id;

            return [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'description' => $transaction->description,
                'direction' => $isIncoming ? 'incoming' : 'outgoing',
                'from_user' => $transaction->userFrom ? [
                    'id' => $transaction->userFrom->id,
                    'name' => $transaction->userFrom->name
                ] : null,
                'to_user' => $transaction->userTo ? [
                    'id' => $transaction->userTo->id,
                    'name' => $transaction->userTo->name
                ] : null,
                'wallet_type' => $transaction->walletTo ? $transaction->walletTo->wallet_type : null,
                'created_at' => $transaction->created_at
            ];
        });

        $transactions->setCollection($transformedTransactions);

        return $this->paginated($transactions, 'Ledger transactions retrieved successfully');
    }

    /**
     * Get income type description
     */
    private function getIncomeTypeDescription(string $type): string
    {
        $descriptions = [
            'level' => 'Level Income - Fixed amount earned from each upline level when downline members purchase packages',
            'fasttrack' => 'Fasttrack Income - Percentage earned from direct referrals when they purchase packages',
            'club' => 'Club Income - Matrix expansion rewards earned when your club matrix fills with new members',
            'company_allocation' => 'Company Allocation - Pool distribution from company allocation fund'
        ];

        return $descriptions[$type] ?? 'Unknown income type';
    }

    /**
     * Get wallet type description
     */
    private function getWalletDescription(string $walletType): string
    {
        $descriptions = [
            'commission' => 'Commission Wallet - Stores Level Income earnings',
            'fasttrack' => 'Fasttrack Wallet - Stores Fasttrack Income earnings',
            'club' => 'Club Wallet - Stores Club Income earnings',
            'autopool' => 'Auto Pool Wallet - Stores Auto Pool Income earnings',
            'main' => 'Main Wallet - General purpose wallet'
        ];

        return $descriptions[$walletType] ?? 'Unknown wallet type';
    }

    /**
     * Get income record description
     */
    private function getIncomeDescription(IncomeRecord $record): string
    {
        $originUserName = $record->originUser ? $record->originUser->name : 'Unknown User';

        $descriptions = [
            'level' => "Level Income from {$originUserName}",
            'fasttrack' => "Fasttrack Income from {$originUserName}",
            'club' => "Club Income - Level {$record->reference_id}",
            'company_allocation' => "Company Allocation from {$originUserName}"
        ];

        return $descriptions[$record->income_type] ?? 'Income earned';
    }

    /**
     * Get dashboard summary with overview statistics
     * GET /api/user/dashboard/summary
     */
    public function dashboardSummary(Request $request)
    {
        $user = JWTAuth::user();

        // Get basic user info
        $userInfo = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'referral_code' => $user->referral_code,
            'current_rank' => $this->getUserRank($user),
            'level' => $this->getUserLevel($user),
        ];

        // Get overview statistics
        $overviewStats = $this->getOverviewStats($user);

        // Get wallet summary
        $walletSummary = $this->getWalletSummary($user);

        $data = [
            'user' => $userInfo,
            'overview_stats' => $overviewStats,
            'wallet_summary' => $walletSummary,
        ];

        return $this->success($data, 'Dashboard summary retrieved successfully');
    }

    /**
     * Get quick stats for dashboard
     * GET /api/user/dashboard/quick-stats
     */
    public function quickStats(Request $request)
    {
        $user = JWTAuth::user();

        $stats = [
            'total_earnings' => IncomeRecord::where('user_id', $user->id)->sum('amount'),
            'this_month_earnings' => IncomeRecord::where('user_id', $user->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount'),
            'last_month_earnings' => IncomeRecord::where('user_id', $user->id)
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('amount'),
            'total_referrals' => User::where('referred_by', $user->id)->count(),
            'active_referrals' => User::where('referred_by', $user->id)->where('status', 'active')->count(),
        ];

        // Calculate percentage change
        if ($stats['last_month_earnings'] > 0) {
            $change = (($stats['this_month_earnings'] - $stats['last_month_earnings']) / $stats['last_month_earnings']) * 100;
            $stats['earnings_change_percentage'] = round($change, 1);
        } else {
            $stats['earnings_change_percentage'] = 0;
        }

        return $this->success($stats, 'Quick stats retrieved successfully');
    }

    /**
     * Get recent activity for dashboard
     * GET /api/user/dashboard/recent-activity
     */
    public function recentActivity(Request $request)
    {
        $user = JWTAuth::user();
        $limit = $request->get('limit', 10);

        $activities = [];

        // Recent income records
        $recentIncomes = IncomeRecord::where('user_id', $user->id)
            ->with('originUser')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        foreach ($recentIncomes as $income) {
            $activities[] = [
                'type' => 'income',
                'title' => $this->getIncomeDescription($income),
                'amount' => $income->amount,
                'date' => $income->created_at,
                'status' => $income->status,
            ];
        }

        // Recent package purchases
        $recentPackages = UserPackage::where('user_id', $user->id)
            ->with('package')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        foreach ($recentPackages as $package) {
            $activities[] = [
                'type' => 'purchase',
                'title' => "Purchased {$package->package->name} Package",
                'amount' => $package->amount_paid,
                'date' => $package->created_at,
                'status' => $package->payment_status,
            ];
        }

        // Sort by date and limit
        usort($activities, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        $activities = array_slice($activities, 0, $limit);

        return $this->success($activities, 'Recent activity retrieved successfully');
    }

    /**
     * Get dashboard widgets
     * GET /api/user/dashboard/widgets
     */
    public function dashboardWidgets(Request $request)
    {
        $user = JWTAuth::user();

        $widgets = [
            'earnings_chart' => $this->getEarningsChartData($user),
            'referral_chart' => $this->getReferralChartData($user),
            'top_earners' => $this->getTopEarners($user),
            'recent_transactions' => $this->getRecentTransactions($user),
        ];

        return $this->success($widgets, 'Dashboard widgets retrieved successfully');
    }

    /**
     * Get user profile
     * GET /api/user/profile
     */
    public function profile(Request $request)
    {
        $user = JWTAuth::user();

        $profile = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'referral_code' => $user->referral_code,
            'referred_by' => $user->referred_by,
            'sponsor_name' => $user->referred_by ? User::find($user->referred_by)->name : null,
            'status' => $user->status,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        // Get referral stats
        $referralStats = [
            'total_referrals' => User::where('referred_by', $user->id)->count(),
            'direct_referrals' => User::where('referred_by', $user->id)->count(),
            'indirect_referrals' => $this->getIndirectReferralsCount($user),
            'referral_earnings' => IncomeRecord::where('user_id', $user->id)
                ->where('income_type', 'fasttrack')
                ->sum('amount'),
        ];

        $data = [
            'user' => $profile,
            'referral_stats' => $referralStats,
        ];

        return $this->success($data, 'Profile retrieved successfully');
    }

    /**
     * Update user profile
     * PUT /api/user/profile
     */
    public function updateProfile(Request $request)
    {
        $user = JWTAuth::user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|unique:users,phone,' . $user->id,
        ]);

        $user->update($validated);

        return $this->success($user->fresh(), 'Profile updated successfully');
    }

    /**
     * Change user password
     * POST /api/user/change-password
     */
    public function changePassword(Request $request)
    {
        $user = JWTAuth::user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return $this->error('Current password is incorrect', 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password'])
        ]);

        return $this->success(null, 'Password changed successfully');
    }

    /**
     * Get network tree
     * GET /api/user/network/tree
     */
    public function networkTree(Request $request)
    {
        $user = JWTAuth::user();

        $treeData = $this->buildNetworkTree($user);

        return $this->success($treeData, 'Network tree retrieved successfully');
    }

    /**
     * Get network statistics
     * GET /api/user/network/stats
     */
    public function networkStats(Request $request)
    {
        $user = JWTAuth::user();

        $stats = [
            'total_members' => $this->getTotalNetworkMembers($user),
            'direct_referrals' => User::where('referred_by', $user->id)->count(),
            'levels_deep' => $this->getNetworkDepth($user),
            'left_leg' => $this->getLeftLegCount($user),
            'right_leg' => $this->getRightLegCount($user),
        ];

        return $this->success($stats, 'Network statistics retrieved successfully');
    }

    /**
     * Get network members
     * GET /api/user/network/members
     */
    public function networkMembers(Request $request)
    {
        $user = JWTAuth::user();
        $level = $request->get('level');
        $status = $request->get('status');
        $perPage = $request->get('per_page', 20);

        $query = User::where('referred_by', $user->id);

        if ($level) {
            $query->whereHas('userTree', function ($q) use ($level) {
                $q->where('depth', $level);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $members = $query->with('userTree')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->paginated($members, 'Network members retrieved successfully');
    }

    /**
     * Get analytics performance
     * GET /api/user/analytics/performance
     */
    public function analyticsPerformance(Request $request)
    {
        $user = JWTAuth::user();

        $performance = [
            'monthly_earnings' => $this->getMonthlyEarnings($user),
            'referral_growth' => $this->getReferralGrowth($user),
            'income_breakdown' => $this->getIncomeBreakdown($user),
            'performance_metrics' => $this->getPerformanceMetrics($user),
        ];

        return $this->success($performance, 'Performance analytics retrieved successfully');
    }

    // Helper methods for dashboard functionality

    private function getUserRank($user)
    {
        // Simple rank calculation based on total earnings
        $totalEarnings = IncomeRecord::where('user_id', $user->id)->sum('amount');

        if ($totalEarnings >= 10000) return 'Diamond Executive';
        if ($totalEarnings >= 5000) return 'Platinum Executive';
        if ($totalEarnings >= 2000) return 'Gold Executive';
        if ($totalEarnings >= 1000) return 'Silver Executive';
        if ($totalEarnings >= 500) return 'Bronze Executive';

        return 'Associate';
    }

    private function getUserLevel($user)
    {
        $userTree = UserTree::where('user_id', $user->id)->first();
        return $userTree ? $userTree->depth + 1 : 1;
    }

    private function getOverviewStats($user)
    {
        $totalEarnings = IncomeRecord::where('user_id', $user->id)->sum('amount');
        $thisMonthEarnings = IncomeRecord::where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        $lastMonthEarnings = IncomeRecord::where('user_id', $user->id)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('amount');

        $earningsChange = 0;
        if ($lastMonthEarnings > 0) {
            $earningsChange = (($thisMonthEarnings - $lastMonthEarnings) / $lastMonthEarnings) * 100;
        }

        return [
            'total_earnings' => $totalEarnings,
            'team_members' => $this->getTotalNetworkMembers($user),
            'direct_referrals' => User::where('referred_by', $user->id)->count(),
            'current_rank' => $this->getUserRank($user),
            'level' => $this->getUserLevel($user),
            'earnings_change' => '+' . round($earningsChange, 1) . '%',
            'team_change' => '+3 this week', // This could be calculated dynamically
            'referrals_change' => '+2 this month', // This could be calculated dynamically
        ];
    }

    private function getWalletSummary($user)
    {
        $wallets = $user->wallets()->get();
        $totalBalance = $wallets->sum('balance');
        $totalPending = $wallets->sum('pending_balance');

        return [
            'total_balance' => $totalBalance,
            'total_pending' => $totalPending,
            'wallets' => $wallets->map(function ($wallet) {
                return [
                    'wallet_type' => $wallet->wallet_type,
                    'balance' => $wallet->balance,
                    'pending_balance' => $wallet->pending_balance,
                ];
            }),
        ];
    }

    private function getEarningsChartData($user)
    {
        // Get last 12 months of earnings
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $earnings = IncomeRecord::where('user_id', $user->id)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('amount');

            $months[] = [
                'month' => $date->format('M Y'),
                'earnings' => $earnings
            ];
        }

        return $months;
    }

    private function getReferralChartData($user)
    {
        // Get last 12 months of referrals
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $referrals = User::where('referred_by', $user->id)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();

            $months[] = [
                'month' => $date->format('M Y'),
                'referrals' => $referrals
            ];
        }

        return $months;
    }

    private function getTopEarners($user)
    {
        // Get top earning referrals
        return User::where('referred_by', $user->id)
            ->withSum('incomeRecords', 'amount')
            ->orderByDesc('income_records_sum_amount')
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'name' => $user->name,
                    'earnings' => $user->income_records_sum_amount ?? 0
                ];
            });
    }

    private function getRecentTransactions($user)
    {
        return LedgerTransaction::where('user_to', $user->id)
            ->with('userFrom')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($transaction) {
                return [
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'from' => $transaction->userFrom->name ?? 'System',
                    'date' => $transaction->created_at,
                ];
            });
    }

    private function getIndirectReferralsCount($user)
    {
        // Count all descendants in the tree
        $userTree = UserTree::where('user_id', $user->id)->first();
        if (!$userTree) return 0;

        return UserTree::where('path', 'like', $userTree->path . $user->id . '/%')->count();
    }

    private function buildNetworkTree($user)
    {
        $userTree = UserTree::where('user_id', $user->id)->first();
        if (!$userTree) {
            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'level' => 0,
                'children' => []
            ];
        }

        return $this->buildTreeRecursive($userTree);
    }

    private function buildTreeRecursive($node, $maxDepth = 3)
    {
        $children = UserTree::where('parent_id', $node->user_id)
            ->with('user')
            ->limit(4) // Max 4 children per node
            ->get();

        $tree = [
            'user_id' => $node->user_id,
            'user_name' => $node->user->name ?? 'Unknown',
            'level' => $node->depth,
            'children' => []
        ];

        if ($node->depth < $maxDepth) {
            foreach ($children as $child) {
                $tree['children'][] = $this->buildTreeRecursive($child, $maxDepth);
            }
        }

        return $tree;
    }

    private function getTotalNetworkMembers($user)
    {
        $userTree = UserTree::where('user_id', $user->id)->first();
        if (!$userTree) return 0;

        return UserTree::where('path', 'like', $userTree->path . $user->id . '/%')->count();
    }

    private function getNetworkDepth($user)
    {
        $userTree = UserTree::where('user_id', $user->id)->first();
        if (!$userTree) return 0;

        $maxDepth = UserTree::where('path', 'like', $userTree->path . $user->id . '/%')
            ->max('depth');

        return $maxDepth ? $maxDepth - $userTree->depth : 0;
    }

    private function getLeftLegCount($user)
    {
        // Count left leg (first 2 children)
        return UserTree::where('parent_id', $user->id)
            ->whereIn('position', [1, 2])
            ->count();
    }

    private function getRightLegCount($user)
    {
        // Count right leg (last 2 children)
        return UserTree::where('parent_id', $user->id)
            ->whereIn('position', [3, 4])
            ->count();
    }

    private function getMonthlyEarnings($user)
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $earnings = IncomeRecord::where('user_id', $user->id)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('amount');

            $months[] = [
                'month' => $date->format('M Y'),
                'earnings' => $earnings
            ];
        }

        return $months;
    }

    private function getReferralGrowth($user)
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $referrals = User::where('referred_by', $user->id)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();

            $months[] = [
                'month' => $date->format('M Y'),
                'referrals' => $referrals
            ];
        }

        return $months;
    }

    private function getIncomeBreakdown($user)
    {
        $types = ['level', 'fasttrack', 'club', 'company_allocation'];
        $breakdown = [];

        foreach ($types as $type) {
            $amount = IncomeRecord::where('user_id', $user->id)
                ->where('income_type', $type)
                ->sum('amount');

            $breakdown[$type] = $amount;
        }

        return $breakdown;
    }

    private function getPerformanceMetrics($user)
    {
        $totalEarnings = IncomeRecord::where('user_id', $user->id)->sum('amount');
        $totalReferrals = User::where('referred_by', $user->id)->count();
        $activeReferrals = User::where('referred_by', $user->id)->where('status', 'active')->count();

        return [
            'total_earnings' => $totalEarnings,
            'total_referrals' => $totalReferrals,
            'active_referrals' => $activeReferrals,
            'conversion_rate' => $totalReferrals > 0 ? ($activeReferrals / $totalReferrals) * 100 : 0,
            'avg_earnings_per_referral' => $totalReferrals > 0 ? $totalEarnings / $totalReferrals : 0,
        ];
    }
}
