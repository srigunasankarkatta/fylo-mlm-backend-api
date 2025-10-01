<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\UserPackage;
use App\Models\User;
use App\Models\LedgerTransaction;
use App\Models\IncomeRecord;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PackageController extends ApiController
{
    /**
     * Display a listing of packages.
     */
    public function index(Request $request)
    {
        $query = Package::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by level
        if ($request->filled('level')) {
            $query->where('level_number', $request->level);
        }

        // Search functionality
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        // Price range filtering
        if ($request->filled('price_from')) {
            $query->where('price', '>=', $request->price_from);
        }
        if ($request->filled('price_to')) {
            $query->where('price', '<=', $request->price_to);
        }

        // Include relationships
        $query->with(['users', 'userPackages', 'incomeConfigs']);

        // Order by
        $orderBy = $request->get('order_by', 'level_number');
        $orderDirection = $request->get('order_direction', 'asc');
        $query->orderBy($orderBy, $orderDirection);

        $packages = $query->paginate($request->get('per_page', 20));

        return $this->paginated($packages, 'Packages retrieved successfully');
    }

    /**
     * Display the specified package.
     */
    public function show($id)
    {
        $package = Package::withTrashed()
            ->with(['users', 'userPackages', 'incomeConfigs'])
            ->find($id);

        if (!$package) {
            return $this->notFound('Package not found');
        }

        return $this->success($package, 'Package retrieved successfully');
    }

    /**
     * Store a newly created package.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:packages,code',
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'level_number' => 'required|integer|min:1|max:10',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $package = Package::create($validated);

        return $this->success($package->load(['users', 'userPackages', 'incomeConfigs']), 'Package created successfully', 201);
    }

    /**
     * Update the specified package.
     */
    public function update(Request $request, $id)
    {
        $package = Package::withTrashed()->find($id);
        if (!$package) {
            return $this->notFound('Package not found');
        }

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('packages')->ignore($package->id)],
            'name' => 'sometimes|string|max:100',
            'price' => 'sometimes|numeric|min:0',
            'level_number' => 'sometimes|integer|min:1|max:10',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $package->update($validated);

        return $this->success($package->load(['users', 'userPackages', 'incomeConfigs']), 'Package updated successfully');
    }

    /**
     * Soft delete the specified package.
     */
    public function destroy($id)
    {
        $package = Package::find($id);
        if (!$package) {
            return $this->notFound('Package not found');
        }

        $package->delete();

        return $this->success(null, 'Package soft-deleted successfully');
    }

    /**
     * Restore a soft-deleted package.
     */
    public function restore($id)
    {
        $package = Package::withTrashed()->find($id);
        if (!$package) {
            return $this->notFound('Package not found');
        }

        if (!$package->trashed()) {
            return $this->error('Package is not deleted', 400);
        }

        $package->restore();

        return $this->success($package->load(['users', 'userPackages', 'incomeConfigs']), 'Package restored successfully');
    }

    /**
     * Permanently delete the specified package.
     */
    public function forceDelete($id)
    {
        $package = Package::withTrashed()->find($id);
        if (!$package) {
            return $this->notFound('Package not found');
        }

        $package->forceDelete();

        return $this->success(null, 'Package permanently deleted');
    }

    /**
     * Get package statistics.
     */
    public function stats($id)
    {
        $package = Package::find($id);
        if (!$package) {
            return $this->notFound('Package not found');
        }

        $stats = [
            'package' => $package,
            'total_users' => $package->users()->count(),
            'total_purchases' => $package->userPackages()->count(),
            'completed_purchases' => $package->userPackages()->completed()->count(),
            'pending_purchases' => $package->userPackages()->pending()->count(),
            'total_revenue' => $package->userPackages()->completed()->sum('amount_paid'),
            'active_income_configs' => $package->incomeConfigs()->active()->count(),
            'recent_purchases' => $package->userPackages()
                ->with('user')
                ->latest()
                ->limit(10)
                ->get(),
        ];

        return $this->success($stats, 'Package statistics retrieved');
    }

    /**
     * Toggle package active status.
     */
    public function toggleStatus($id)
    {
        $package = Package::find($id);
        if (!$package) {
            return $this->notFound('Package not found');
        }

        $package->update(['is_active' => !$package->is_active]);

        return $this->success($package, 'Package status updated successfully');
    }

    /**
     * Get package transactions with comprehensive filters.
     */
    public function transactions(Request $request)
    {
        $query = UserPackage::with(['user', 'package']);

        // Filter by package ID
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        // Filter by user ID
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by payment status array
        if ($request->filled('payment_statuses')) {
            $statuses = is_array($request->payment_statuses)
                ? $request->payment_statuses
                : explode(',', $request->payment_statuses);
            $query->whereIn('payment_status', $statuses);
        }

        // Filter by assigned level
        if ($request->filled('assigned_level')) {
            $query->where('assigned_level', $request->assigned_level);
        }

        // Filter by amount range
        if ($request->filled('amount_from')) {
            $query->where('amount_paid', '>=', $request->amount_from);
        }
        if ($request->filled('amount_to')) {
            $query->where('amount_paid', '<=', $request->amount_to);
        }

        // Filter by date range
        if ($request->filled('purchase_from')) {
            $query->where('purchase_at', '>=', $request->purchase_from);
        }
        if ($request->filled('purchase_to')) {
            $query->where('purchase_at', '<=', $request->purchase_to);
        }

        // Filter by created date range
        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }
        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', $request->created_to);
        }

        // Filter by processing status
        if ($request->filled('processing')) {
            $query->where('processing', $request->boolean('processing'));
        }

        // Filter by processed status
        if ($request->filled('processed')) {
            if ($request->boolean('processed')) {
                $query->whereNotNull('processed_at');
            } else {
                $query->whereNull('processed_at');
            }
        }

        // Search functionality
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($builder) use ($q) {
                $builder->where('payment_reference', 'like', "%{$q}%")
                    ->orWhere('idempotency_key', 'like', "%{$q}%")
                    ->orWhereHas('user', function ($userQuery) use ($q) {
                        $userQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%")
                            ->orWhere('referral_code', 'like', "%{$q}%");
                    })
                    ->orWhereHas('package', function ($packageQuery) use ($q) {
                        $packageQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%");
                    });
            });
        }

        // Order by
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $transactions = $query->paginate($request->get('per_page', 20));

        return $this->paginated($transactions, 'Package transactions retrieved successfully');
    }

    /**
     * Get transaction statistics and analytics.
     */
    public function transactionStats(Request $request)
    {
        $query = UserPackage::query();

        // Apply same filters as transactions method
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('purchase_from')) {
            $query->where('purchase_at', '>=', $request->purchase_from);
        }
        if ($request->filled('purchase_to')) {
            $query->where('purchase_at', '<=', $request->purchase_to);
        }

        // Basic statistics
        $totalTransactions = $query->count();
        $completedTransactions = (clone $query)->where('payment_status', 'completed')->count();
        $pendingTransactions = (clone $query)->where('payment_status', 'pending')->count();
        $failedTransactions = (clone $query)->where('payment_status', 'failed')->count();

        // Revenue statistics
        $totalRevenue = (clone $query)->where('payment_status', 'completed')->sum('amount_paid');
        $averageTransactionValue = $completedTransactions > 0
            ? $totalRevenue / $completedTransactions
            : 0;

        // Processing statistics
        $processedTransactions = (clone $query)->whereNotNull('processed_at')->count();
        $unprocessedTransactions = $totalTransactions - $processedTransactions;

        // Package breakdown
        $packageBreakdown = (clone $query)
            ->join('packages', 'user_packages.package_id', '=', 'packages.id')
            ->select('packages.name', 'packages.code', 'packages.level_number')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('SUM(CASE WHEN payment_status = "completed" THEN amount_paid ELSE 0 END) as total_revenue')
            ->groupBy('packages.id', 'packages.name', 'packages.code', 'packages.level_number')
            ->orderBy('transaction_count', 'desc')
            ->get();

        // Status breakdown
        $statusBreakdown = (clone $query)
            ->select('payment_status')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(CASE WHEN payment_status = "completed" THEN amount_paid ELSE 0 END) as revenue')
            ->groupBy('payment_status')
            ->get();

        // Daily statistics (last 30 days)
        $dailyStats = (clone $query)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw('SUM(CASE WHEN payment_status = "completed" THEN amount_paid ELSE 0 END) as revenue')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Recent transactions
        $recentTransactions = (clone $query)
            ->with(['user', 'package'])
            ->latest()
            ->limit(10)
            ->get();

        return $this->success([
            'overview' => [
                'total_transactions' => $totalTransactions,
                'completed_transactions' => $completedTransactions,
                'pending_transactions' => $pendingTransactions,
                'failed_transactions' => $failedTransactions,
                'total_revenue' => $totalRevenue,
                'average_transaction_value' => $averageTransactionValue,
                'processed_transactions' => $processedTransactions,
                'unprocessed_transactions' => $unprocessedTransactions,
            ],
            'package_breakdown' => $packageBreakdown,
            'status_breakdown' => $statusBreakdown,
            'daily_stats' => $dailyStats,
            'recent_transactions' => $recentTransactions,
        ], 'Transaction statistics retrieved successfully');
    }

    /**
     * Get transaction details by ID.
     */
    public function transactionDetails($id)
    {
        $transaction = UserPackage::with(['user', 'package'])
            ->find($id);

        if (!$transaction) {
            return $this->notFound('Transaction not found');
        }

        // Get related income records
        $incomeRecords = IncomeRecord::where('user_id', $transaction->user_id)
            ->where('reference_id', 'like', "%{$transaction->id}%")
            ->with('incomeConfig')
            ->get();

        // Get related ledger transactions
        $ledgerTransactions = LedgerTransaction::where('user_from', $transaction->user_id)
            ->orWhere('user_to', $transaction->user_id)
            ->where('created_at', '>=', $transaction->created_at)
            ->where('created_at', '<=', $transaction->created_at->addMinutes(10))
            ->get();

        return $this->success([
            'transaction' => $transaction,
            'income_records' => $incomeRecords,
            'ledger_transactions' => $ledgerTransactions,
        ], 'Transaction details retrieved successfully');
    }

    /**
     * Update transaction status (admin override).
     */
    public function updateTransactionStatus(Request $request, $id)
    {
        $transaction = UserPackage::find($id);
        if (!$transaction) {
            return $this->notFound('Transaction not found');
        }

        $validated = $request->validate([
            'payment_status' => 'required|in:pending,completed,failed',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $transaction->update([
            'payment_status' => $validated['payment_status'],
            'payment_reference' => $validated['payment_reference'] ?? $transaction->payment_reference,
            'payment_meta' => array_merge($transaction->payment_meta ?? [], [
                'admin_notes' => $validated['notes'] ?? null,
                'admin_updated_at' => now()->toISOString(),
            ]),
        ]);

        return $this->success($transaction->load(['user', 'package']), 'Transaction status updated successfully');
    }

    /**
     * Export transactions to CSV.
     */
    public function exportTransactions(Request $request)
    {
        $query = UserPackage::with(['user', 'package']);

        // Apply same filters as transactions method
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('purchase_from')) {
            $query->where('purchase_at', '>=', $request->purchase_from);
        }
        if ($request->filled('purchase_to')) {
            $query->where('purchase_at', '<=', $request->purchase_to);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $csvData = [];
        $csvData[] = [
            'ID',
            'User Name',
            'User Email',
            'User Phone',
            'Package Name',
            'Package Code',
            'Level',
            'Amount Paid',
            'Payment Status',
            'Payment Reference',
            'Purchase Date',
            'Processed At',
            'Created At',
        ];

        foreach ($transactions as $transaction) {
            $csvData[] = [
                $transaction->id,
                $transaction->user->name ?? 'N/A',
                $transaction->user->email ?? 'N/A',
                $transaction->user->phone ?? 'N/A',
                $transaction->package->name ?? 'N/A',
                $transaction->package->code ?? 'N/A',
                $transaction->assigned_level ?? 'N/A',
                $transaction->amount_paid,
                $transaction->payment_status,
                $transaction->payment_reference ?? 'N/A',
                $transaction->purchase_at ? $transaction->purchase_at->format('Y-m-d H:i:s') : 'N/A',
                $transaction->processed_at ? $transaction->processed_at->format('Y-m-d H:i:s') : 'N/A',
                $transaction->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $filename = 'package_transactions_' . now()->format('Y-m-d_H-i-s') . '.csv';

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
     * Get transaction dashboard data.
     */
    public function transactionDashboard(Request $request)
    {
        $period = $request->get('period', '30'); // days
        $startDate = now()->subDays($period);

        // Overall statistics
        $totalTransactions = UserPackage::where('created_at', '>=', $startDate)->count();
        $completedTransactions = UserPackage::where('created_at', '>=', $startDate)
            ->where('payment_status', 'completed')->count();
        $totalRevenue = UserPackage::where('created_at', '>=', $startDate)
            ->where('payment_status', 'completed')->sum('amount_paid');

        // Chart data - daily transactions
        $dailyData = UserPackage::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN payment_status = "completed" THEN 1 ELSE 0 END) as completed')
            ->selectRaw('SUM(CASE WHEN payment_status = "completed" THEN amount_paid ELSE 0 END) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top packages
        $topPackages = UserPackage::where('created_at', '>=', $startDate)
            ->join('packages', 'user_packages.package_id', '=', 'packages.id')
            ->select('packages.name', 'packages.code')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('SUM(CASE WHEN payment_status = "completed" THEN amount_paid ELSE 0 END) as revenue')
            ->groupBy('packages.id', 'packages.name', 'packages.code')
            ->orderBy('transaction_count', 'desc')
            ->limit(5)
            ->get();

        // Recent activity
        $recentActivity = UserPackage::with(['user', 'package'])
            ->where('created_at', '>=', $startDate)
            ->latest()
            ->limit(20)
            ->get();

        return $this->success([
            'overview' => [
                'total_transactions' => $totalTransactions,
                'completed_transactions' => $completedTransactions,
                'completion_rate' => $totalTransactions > 0 ? ($completedTransactions / $totalTransactions) * 100 : 0,
                'total_revenue' => $totalRevenue,
                'average_transaction_value' => $completedTransactions > 0 ? $totalRevenue / $completedTransactions : 0,
            ],
            'chart_data' => $dailyData,
            'top_packages' => $topPackages,
            'recent_activity' => $recentActivity,
        ], 'Transaction dashboard data retrieved successfully');
    }
}
