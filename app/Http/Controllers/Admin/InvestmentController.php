<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Models\UserInvestment;
use App\Models\InvestmentPlan;
use App\Models\User;
use App\Models\LedgerTransaction;
use App\Models\IncomeRecord;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvestmentController extends ApiController
{
    /**
     * Get investment transactions with comprehensive filters.
     */
    public function transactions(Request $request)
    {
        $query = UserInvestment::with(['user', 'investmentPlan', 'referrer']);

        // Filter by investment plan ID
        if ($request->filled('plan_id')) {
            $query->where('investment_plan_id', $request->plan_id);
        }

        // Filter by user ID
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by status array
        if ($request->filled('statuses')) {
            $statuses = is_array($request->statuses)
                ? $request->statuses
                : explode(',', $request->statuses);
            $query->whereIn('status', $statuses);
        }

        // Filter by amount range
        if ($request->filled('amount_from')) {
            $query->where('amount', '>=', $request->amount_from);
        }
        if ($request->filled('amount_to')) {
            $query->where('amount', '<=', $request->amount_to);
        }

        // Filter by date range
        if ($request->filled('invested_from')) {
            $query->where('invested_at', '>=', $request->invested_from);
        }
        if ($request->filled('invested_to')) {
            $query->where('invested_at', '<=', $request->invested_to);
        }

        // Filter by start date range
        if ($request->filled('start_from')) {
            $query->where('start_at', '>=', $request->start_from);
        }
        if ($request->filled('start_to')) {
            $query->where('start_at', '<=', $request->start_to);
        }

        // Filter by end date range
        if ($request->filled('end_from')) {
            $query->where('end_at', '>=', $request->end_from);
        }
        if ($request->filled('end_to')) {
            $query->where('end_at', '<=', $request->end_to);
        }

        // Filter by created date range
        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }
        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', $request->created_to);
        }

        // Filter by duration
        if ($request->filled('duration_days')) {
            $query->where('duration_days', $request->duration_days);
        }

        // Filter by daily profit percent range
        if ($request->filled('profit_from')) {
            $query->where('daily_profit_percent', '>=', $request->profit_from);
        }
        if ($request->filled('profit_to')) {
            $query->where('daily_profit_percent', '<=', $request->profit_to);
        }

        // Filter by referral commission
        if ($request->filled('has_referral')) {
            if ($request->boolean('has_referral')) {
                $query->whereNotNull('referrer_id');
            } else {
                $query->whereNull('referrer_id');
            }
        }

        // Search functionality
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($builder) use ($q) {
                $builder->where('id', 'like', "%{$q}%")
                    ->orWhereHas('user', function ($userQuery) use ($q) {
                        $userQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%")
                            ->orWhere('referral_code', 'like', "%{$q}%");
                    })
                    ->orWhereHas('investmentPlan', function ($planQuery) use ($q) {
                        $planQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%");
                    })
                    ->orWhereHas('referrer', function ($referrerQuery) use ($q) {
                        $referrerQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    });
            });
        }

        // Order by
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $transactions = $query->paginate($request->get('per_page', 20));

        return $this->paginated($transactions, 'Investment transactions retrieved successfully');
    }

    /**
     * Get investment transaction statistics and analytics.
     */
    public function transactionStats(Request $request)
    {
        $query = UserInvestment::query();

        // Apply same filters as transactions method
        if ($request->filled('plan_id')) {
            $query->where('investment_plan_id', $request->plan_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('invested_from')) {
            $query->where('invested_at', '>=', $request->invested_from);
        }
        if ($request->filled('invested_to')) {
            $query->where('invested_at', '<=', $request->invested_to);
        }

        // Basic statistics
        $totalInvestments = $query->count();
        $activeInvestments = (clone $query)->where('status', 'active')->count();
        $pendingInvestments = (clone $query)->where('status', 'pending')->count();
        $completedInvestments = (clone $query)->where('status', 'completed')->count();
        $cancelledInvestments = (clone $query)->where('status', 'cancelled')->count();

        // Financial statistics
        $totalInvestedAmount = (clone $query)->sum('amount');
        $totalAccruedInterest = (clone $query)->sum('accrued_interest');
        $totalPayouts = (clone $query)->sum('total_payout');
        $totalReferralCommissions = (clone $query)->sum('referral_commission');

        // Plan breakdown
        $planBreakdown = (clone $query)
            ->join('investment_plans', 'user_investments.investment_plan_id', '=', 'investment_plans.id')
            ->select('investment_plans.name', 'investment_plans.code', 'investment_plans.daily_profit_percent')
            ->selectRaw('COUNT(*) as investment_count')
            ->selectRaw('SUM(amount) as total_invested')
            ->selectRaw('SUM(accrued_interest) as total_interest')
            ->selectRaw('SUM(total_payout) as total_payouts')
            ->groupBy('investment_plans.id', 'investment_plans.name', 'investment_plans.code', 'investment_plans.daily_profit_percent')
            ->orderBy('investment_count', 'desc')
            ->get();

        // Status breakdown
        $statusBreakdown = (clone $query)
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(amount) as total_invested')
            ->selectRaw('SUM(accrued_interest) as total_interest')
            ->selectRaw('SUM(total_payout) as total_payouts')
            ->groupBy('status')
            ->get();

        // Daily statistics (last 30 days)
        $dailyStats = (clone $query)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as investments')
            ->selectRaw('SUM(amount) as total_invested')
            ->selectRaw('SUM(accrued_interest) as total_interest')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Recent transactions
        $recentTransactions = (clone $query)
            ->with(['user', 'investmentPlan'])
            ->latest()
            ->limit(10)
            ->get();

        return $this->success([
            'overview' => [
                'total_investments' => $totalInvestments,
                'active_investments' => $activeInvestments,
                'pending_investments' => $pendingInvestments,
                'completed_investments' => $completedInvestments,
                'cancelled_investments' => $cancelledInvestments,
                'total_invested_amount' => $totalInvestedAmount,
                'total_accrued_interest' => $totalAccruedInterest,
                'total_payouts' => $totalPayouts,
                'total_referral_commissions' => $totalReferralCommissions,
            ],
            'plan_breakdown' => $planBreakdown,
            'status_breakdown' => $statusBreakdown,
            'daily_stats' => $dailyStats,
            'recent_transactions' => $recentTransactions,
        ], 'Investment transaction statistics retrieved successfully');
    }

    /**
     * Get investment transaction details by ID.
     */
    public function transactionDetails($id)
    {
        $transaction = UserInvestment::with(['user', 'investmentPlan', 'referrer'])
            ->find($id);

        if (!$transaction) {
            return $this->notFound('Investment transaction not found');
        }

        // Get related income records
        $incomeRecords = IncomeRecord::where('user_id', $transaction->user_id)
            ->where('reference_id', 'like', "%investment_{$transaction->id}%")
            ->with('incomeConfig')
            ->get();

        // Get related ledger transactions
        $ledgerTransactions = LedgerTransaction::where('user_from', $transaction->user_id)
            ->orWhere('user_to', $transaction->user_id)
            ->where('created_at', '>=', $transaction->created_at)
            ->where('created_at', '<=', $transaction->created_at->addMinutes(10))
            ->get();

        // Get user's wallet balances
        $userWallets = Wallet::where('user_id', $transaction->user_id)->get();

        return $this->success([
            'transaction' => $transaction,
            'income_records' => $incomeRecords,
            'ledger_transactions' => $ledgerTransactions,
            'user_wallets' => $userWallets,
        ], 'Investment transaction details retrieved successfully');
    }

    /**
     * Update investment transaction status (admin override).
     */
    public function updateTransactionStatus(Request $request, $id)
    {
        $transaction = UserInvestment::find($id);
        if (!$transaction) {
            return $this->notFound('Investment transaction not found');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,active,completed,cancelled,withdrawn',
            'notes' => 'nullable|string|max:1000',
        ]);

        $oldStatus = $transaction->status;

        $transaction->update([
            'status' => $validated['status'],
            'metadata' => array_merge($transaction->metadata ?? [], [
                'admin_notes' => $validated['notes'] ?? null,
                'admin_updated_at' => now()->toISOString(),
                'status_changed_from' => $oldStatus,
            ]),
        ]);

        return $this->success($transaction->load(['user', 'investmentPlan', 'referrer']), 'Investment transaction status updated successfully');
    }

    /**
     * Export investment transactions to CSV.
     */
    public function exportTransactions(Request $request)
    {
        $query = UserInvestment::with(['user', 'investmentPlan', 'referrer']);

        // Apply same filters as transactions method
        if ($request->filled('plan_id')) {
            $query->where('investment_plan_id', $request->plan_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('invested_from')) {
            $query->where('invested_at', '>=', $request->invested_from);
        }
        if ($request->filled('invested_to')) {
            $query->where('invested_at', '<=', $request->invested_to);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $csvData = [];
        $csvData[] = [
            'ID',
            'User Name',
            'User Email',
            'User Phone',
            'Plan Name',
            'Plan Code',
            'Amount',
            'Daily Profit %',
            'Duration Days',
            'Status',
            'Invested At',
            'Start At',
            'End At',
            'Matured At',
            'Accrued Interest',
            'Total Payout',
            'Referrer Name',
            'Referral Commission',
            'Created At',
        ];

        foreach ($transactions as $transaction) {
            $csvData[] = [
                $transaction->id,
                $transaction->user->name ?? 'N/A',
                $transaction->user->email ?? 'N/A',
                $transaction->user->phone ?? 'N/A',
                $transaction->investmentPlan->name ?? 'N/A',
                $transaction->investmentPlan->code ?? 'N/A',
                $transaction->amount,
                $transaction->daily_profit_percent,
                $transaction->duration_days,
                $transaction->status,
                $transaction->invested_at ? $transaction->invested_at->format('Y-m-d H:i:s') : 'N/A',
                $transaction->start_at ? $transaction->start_at->format('Y-m-d H:i:s') : 'N/A',
                $transaction->end_at ? $transaction->end_at->format('Y-m-d H:i:s') : 'N/A',
                $transaction->matured_at ? $transaction->matured_at->format('Y-m-d H:i:s') : 'N/A',
                $transaction->accrued_interest,
                $transaction->total_payout,
                $transaction->referrer->name ?? 'N/A',
                $transaction->referral_commission,
                $transaction->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $filename = 'investment_transactions_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($csvData) {
            $file = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get investment transaction dashboard data.
     */
    public function transactionDashboard(Request $request)
    {
        $period = $request->get('period', '30'); // days
        $startDate = now()->subDays($period);

        // Overall statistics
        $totalInvestments = UserInvestment::where('created_at', '>=', $startDate)->count();
        $activeInvestments = UserInvestment::where('created_at', '>=', $startDate)
            ->where('status', 'active')->count();
        $totalInvestedAmount = UserInvestment::where('created_at', '>=', $startDate)
            ->sum('amount');
        $totalAccruedInterest = UserInvestment::where('created_at', '>=', $startDate)
            ->sum('accrued_interest');

        // Chart data - daily investments
        $dailyData = UserInvestment::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(amount) as total_invested')
            ->selectRaw('SUM(accrued_interest) as total_interest')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top plans
        $topPlans = UserInvestment::where('created_at', '>=', $startDate)
            ->join('investment_plans', 'user_investments.investment_plan_id', '=', 'investment_plans.id')
            ->select('investment_plans.name', 'investment_plans.code')
            ->selectRaw('COUNT(*) as investment_count')
            ->selectRaw('SUM(amount) as total_invested')
            ->groupBy('investment_plans.id', 'investment_plans.name', 'investment_plans.code')
            ->orderBy('investment_count', 'desc')
            ->limit(5)
            ->get();

        // Recent activity
        $recentActivity = UserInvestment::with(['user', 'investmentPlan'])
            ->where('created_at', '>=', $startDate)
            ->latest()
            ->limit(20)
            ->get();

        return $this->success([
            'overview' => [
                'total_investments' => $totalInvestments,
                'active_investments' => $activeInvestments,
                'total_invested_amount' => $totalInvestedAmount,
                'total_accrued_interest' => $totalAccruedInterest,
                'average_investment_value' => $totalInvestments > 0 ? $totalInvestedAmount / $totalInvestments : 0,
            ],
            'chart_data' => $dailyData,
            'top_plans' => $topPlans,
            'recent_activity' => $recentActivity,
        ], 'Investment transaction dashboard data retrieved successfully');
    }

    /**
     * Activate investment (admin function).
     */
    public function activateInvestment(Request $request, $id)
    {
        $transaction = UserInvestment::find($id);
        if (!$transaction) {
            return $this->notFound('Investment transaction not found');
        }

        if ($transaction->status !== 'pending') {
            return $this->error('Only pending investments can be activated', 400);
        }

        try {
            DB::beginTransaction();

            // Check if user has sufficient wallet balance
            $userWallet = Wallet::getOrCreate($transaction->user_id, 'main', 'USD');
            if ($userWallet->balance < $transaction->amount) {
                return $this->error('Insufficient wallet balance for activation', 400);
            }

            // Deduct amount from user's wallet
            $userWallet->subtractBalance($transaction->amount);

            // Create ledger transaction for payment
            LedgerTransaction::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'user_from' => $transaction->user_id,
                'user_to' => null, // Company
                'wallet_from_id' => $userWallet->id,
                'wallet_to_id' => null, // Company wallet
                'type' => 'investment_payment',
                'amount' => $transaction->amount,
                'currency' => 'USD',
                'reference_id' => "investment_{$transaction->id}_payment",
                'description' => "Investment payment for {$transaction->investmentPlan->name}",
            ]);

            // Process referral commission if applicable
            if ($transaction->referrer_id && $transaction->referral_commission > 0) {
                $referrerWallet = Wallet::getOrCreate($transaction->referrer_id, 'main', 'USD');
                $referrerWallet->addBalance($transaction->referral_commission);

                LedgerTransaction::create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'user_from' => null, // Company
                    'user_to' => $transaction->referrer_id,
                    'wallet_from_id' => null, // Company wallet
                    'wallet_to_id' => $referrerWallet->id,
                    'type' => 'investment_referral',
                    'amount' => $transaction->referral_commission,
                    'currency' => 'USD',
                    'reference_id' => "investment_{$transaction->id}_referral",
                    'description' => "Referral commission for investment #{$transaction->id}",
                ]);
            }

            // Activate the investment
            $transaction->activate();

            DB::commit();

            return $this->success($transaction->load(['user', 'investmentPlan', 'referrer']), 'Investment activated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to activate investment: ' . $e->getMessage(), 500);
        }
    }
}
