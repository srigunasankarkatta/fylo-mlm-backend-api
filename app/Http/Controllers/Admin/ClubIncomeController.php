<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Models\ClubMatrix;
use App\Models\IncomeRecord;
use App\Models\LedgerTransaction;
use App\Models\IncomeConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClubIncomeController extends ApiController
{
    /**
     * Display a listing of club income records.
     */
    public function index(Request $request)
    {
        $query = ClubMatrix::with(['sponsor', 'member']);

        // Filter by level
        if ($request->filled('level')) {
            $query->atLevel($request->level);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->withStatus($request->status);
        }

        // Filter by sponsor
        if ($request->filled('sponsor_id')) {
            $query->forSponsor($request->sponsor_id);
        }

        // Filter by member
        if ($request->filled('member_id')) {
            $query->forMember($request->member_id);
        }

        // Filter by depth
        if ($request->filled('depth')) {
            $query->atDepth($request->depth);
        }

        // Search functionality
        if ($request->filled('search')) {
            $q = $request->search;
            $query->whereHas('sponsor', function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })->orWhereHas('member', function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        // Order by
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $records = $query->paginate($request->get('per_page', 20));

        return $this->paginated($records, 'Club income records retrieved successfully');
    }

    /**
     * Display the specified club income record.
     */
    public function show($id)
    {
        $record = ClubMatrix::withTrashed()
            ->with(['sponsor', 'member'])
            ->find($id);

        if (!$record) {
            return $this->notFound('Club income record not found');
        }

        return $this->success($record, 'Club income record retrieved successfully');
    }

    /**
     * Store a newly created club income record.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sponsor_id' => 'required|exists:users,id',
            'member_id' => 'required|exists:users,id',
            'level' => 'required|integer|min:1|max:10',
            'position' => 'nullable|integer|min:1|max:4',
            'status' => ['nullable', Rule::in(['active', 'completed', 'paid_out'])],
            'depth' => 'nullable|integer|min:1'
        ]);

        // Check for duplicate sponsor-member relationship
        $existing = ClubMatrix::where('sponsor_id', $validated['sponsor_id'])
            ->where('member_id', $validated['member_id'])
            ->first();

        if ($existing) {
            return $this->error('Club relationship already exists between these users', 400);
        }

        $record = ClubMatrix::create($validated);

        return $this->success($record->load(['sponsor', 'member']), 'Club income record created successfully', 201);
    }

    /**
     * Update the specified club income record.
     */
    public function update(Request $request, $id)
    {
        $record = ClubMatrix::withTrashed()->find($id);
        if (!$record) {
            return $this->notFound('Club income record not found');
        }

        $validated = $request->validate([
            'level' => 'sometimes|integer|min:1|max:10',
            'position' => 'nullable|integer|min:1|max:4',
            'status' => ['sometimes', Rule::in(['active', 'completed', 'paid_out'])],
            'depth' => 'sometimes|integer|min:1'
        ]);

        $record->update($validated);

        return $this->success($record->load(['sponsor', 'member']), 'Club income record updated successfully');
    }

    /**
     * Soft delete the specified club income record.
     */
    public function destroy($id)
    {
        $record = ClubMatrix::find($id);
        if (!$record) {
            return $this->notFound('Club income record not found');
        }

        $record->delete();

        return $this->success(null, 'Club income record soft-deleted successfully');
    }

    /**
     * Restore a soft-deleted club income record.
     */
    public function restore($id)
    {
        $record = ClubMatrix::withTrashed()->find($id);
        if (!$record) {
            return $this->notFound('Club income record not found');
        }

        if (!$record->trashed()) {
            return $this->error('Club income record is not deleted', 400);
        }

        $record->restore();

        return $this->success($record->load(['sponsor', 'member']), 'Club income record restored successfully');
    }

    /**
     * Permanently delete the specified club income record.
     */
    public function forceDelete($id)
    {
        $record = ClubMatrix::withTrashed()->find($id);
        if (!$record) {
            return $this->notFound('Club income record not found');
        }

        $record->forceDelete();

        return $this->success(null, 'Club income record permanently deleted');
    }

    /**
     * Get club income record statistics.
     */
    public function stats($id)
    {
        $record = ClubMatrix::find($id);
        if (!$record) {
            return $this->notFound('Club income record not found');
        }

        $stats = [
            'record' => $record,
            'sponsor_stats' => ClubMatrix::getSponsorStats($record->sponsor_id),
            'member_stats' => ClubMatrix::getMemberStats($record->member_id),
            'level_stats' => ClubMatrix::getLevelStats()[$record->level] ?? null,
        ];

        return $this->success($stats, 'Club income record statistics retrieved');
    }

    /**
     * Toggle club income record status.
     */
    public function toggleStatus($id)
    {
        $record = ClubMatrix::find($id);
        if (!$record) {
            return $this->notFound('Club income record not found');
        }

        $newStatus = match ($record->status) {
            'active' => 'completed',
            'completed' => 'paid_out',
            'paid_out' => 'active',
            default => 'active'
        };

        $record->update(['status' => $newStatus]);

        return $this->success($record, 'Club income record status updated successfully');
    }

    /**
     * Process payout for a club income record.
     */
    public function payout($id)
    {
        $record = ClubMatrix::with(['sponsor', 'member'])->find($id);
        if (!$record) {
            return $this->notFound('Club income record not found');
        }

        if ($record->status === 'paid_out') {
            return $this->error('Already paid out', 400);
        }

        if ($record->status !== 'completed') {
            return $this->error('Record must be completed before payout', 400);
        }

        try {
            DB::transaction(function () use ($record) {
                // Get effective income configuration
                $incomeConfig = IncomeConfig::getEffectiveConfig(
                    'club',
                    null, // Global config
                    $record->level,
                    null, // No sub-level for club
                    now()
                );

                if (!$incomeConfig) {
                    throw new \Exception('No effective income configuration found for club level ' . $record->level);
                }

                // Calculate payout amount (this would typically be based on package purchase amount)
                // For now, using a fixed amount - in real implementation, this would come from package data
                $baseAmount = 1000; // This should come from the member's package purchase
                $payoutAmount = $incomeConfig->calculateIncome($baseAmount);

                // Create income record
                $income = IncomeRecord::create([
                    'user_id' => $record->member_id,
                    'origin_user_id' => $record->sponsor_id,
                    'income_config_id' => $incomeConfig->id,
                    'income_type' => 'club',
                    'amount' => $payoutAmount,
                    'currency' => 'USD',
                    'status' => 'paid'
                ]);

                // Create ledger transaction
                LedgerTransaction::create([
                    'user_to' => $record->member_id,
                    'type' => 'club_income',
                    'amount' => $payoutAmount,
                    'currency' => 'USD',
                    'reference_id' => $income->id,
                    'description' => "Club income payout - Level {$record->level}"
                ]);

                // Update record status
                $record->markAsPaidOut();
            });

            return $this->success(null, 'Club income paid out successfully');
        } catch (\Exception $e) {
            return $this->error('Payout failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Process bulk payouts for completed records.
     */
    public function bulkPayout(Request $request)
    {
        $validated = $request->validate([
            'level' => 'nullable|integer|min:1|max:10',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $query = ClubMatrix::completed()->with(['sponsor', 'member']);

        if ($validated['level']) {
            $query->atLevel($validated['level']);
        }

        $records = $query->limit($validated['limit'] ?? 50)->get();

        if ($records->isEmpty()) {
            return $this->error('No completed records found for payout', 404);
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($records as $record) {
            try {
                DB::transaction(function () use ($record) {
                    // Get effective income configuration
                    $incomeConfig = IncomeConfig::getEffectiveConfig(
                        'club',
                        null,
                        $record->level,
                        null,
                        now()
                    );

                    if (!$incomeConfig) {
                        throw new \Exception('No effective income configuration found');
                    }

                    $baseAmount = 1000; // This should come from package data
                    $payoutAmount = $incomeConfig->calculateIncome($baseAmount);

                    // Create income record
                    $income = IncomeRecord::create([
                        'user_id' => $record->member_id,
                        'origin_user_id' => $record->sponsor_id,
                        'income_config_id' => $incomeConfig->id,
                        'income_type' => 'club',
                        'amount' => $payoutAmount,
                        'currency' => 'USD',
                        'status' => 'paid'
                    ]);

                    // Create ledger transaction
                    LedgerTransaction::create([
                        'user_to' => $record->member_id,
                        'type' => 'club_income',
                        'amount' => $payoutAmount,
                        'currency' => 'USD',
                        'reference_id' => $income->id,
                        'description' => "Club income bulk payout - Level {$record->level}"
                    ]);

                    $record->markAsPaidOut();
                });

                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "Record {$record->id}: " . $e->getMessage();
            }
        }

        return $this->success([
            'total_processed' => $records->count(),
            'successful' => $successCount,
            'failed' => $errorCount,
            'errors' => $errors
        ], "Bulk payout completed: {$successCount} successful, {$errorCount} failed");
    }

    /**
     * Get club income dashboard statistics.
     */
    public function dashboard()
    {
        $stats = ClubMatrix::getPayoutStats();
        $levelStats = ClubMatrix::getLevelStats();
        $recentRecords = ClubMatrix::with(['sponsor', 'member'])
            ->latest()
            ->limit(10)
            ->get();

        return $this->success([
            'overview' => $stats,
            'by_level' => $levelStats,
            'recent_records' => $recentRecords
        ], 'Club income dashboard data retrieved');
    }

    /**
     * Get entries ready for payout.
     */
    public function readyForPayout(Request $request)
    {
        $level = $request->get('level');
        $records = ClubMatrix::getEntriesReadyForPayout($level);

        return $this->success($records, 'Records ready for payout retrieved');
    }

    /**
     * Mark record as completed.
     */
    public function markCompleted($id)
    {
        $record = ClubMatrix::find($id);
        if (!$record) {
            return $this->notFound('Club income record not found');
        }

        $record->markAsCompleted();

        return $this->success($record, 'Club income record marked as completed');
    }

    /**
     * Get club matrix visualization for a sponsor.
     */
    public function matrixVisualization(Request $request, $sponsorId)
    {
        $maxDepth = $request->get('max_depth', 5);
        $matrix = ClubMatrix::getMatrixVisualization($sponsorId, $maxDepth);

        return $this->success($matrix, 'Club matrix visualization retrieved');
    }
}
