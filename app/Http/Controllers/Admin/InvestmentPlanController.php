<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Models\InvestmentPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class InvestmentPlanController extends ApiController
{
    /**
     * Display a listing of investment plans.
     */
    public function index(Request $request)
    {
        $query = InvestmentPlan::query();

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
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

        // Filter by version
        if ($request->filled('version')) {
            $query->where('version', $request->version);
        }

        // Filter by amount range
        if ($request->filled('min_amount')) {
            $query->where('min_amount', '<=', $request->min_amount);
        }
        if ($request->filled('max_amount')) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('max_amount')
                    ->orWhere('max_amount', '>=', $request->max_amount);
            });
        }

        // Filter by profit range
        if ($request->filled('min_profit_percent')) {
            $query->where('daily_profit_percent', '>=', $request->min_profit_percent);
        }
        if ($request->filled('max_profit_percent')) {
            $query->where('daily_profit_percent', '<=', $request->max_profit_percent);
        }

        // Date range filtering
        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }
        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', $request->created_to);
        }

        // Include relationships
        $query->with(['creator', 'updater']);

        // Order by
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $plans = $query->paginate($request->get('per_page', 20));

        return $this->paginated($plans, 'Investment plans retrieved successfully');
    }

    /**
     * Display the specified investment plan.
     */
    public function show($id)
    {
        $plan = InvestmentPlan::withTrashed()
            ->with(['creator', 'updater', 'investments'])
            ->find($id);

        if (!$plan) {
            return $this->notFound('Investment plan not found');
        }

        // Add statistics to the response
        $plan->stats = $plan->getStats();

        return $this->success($plan, 'Investment plan retrieved successfully');
    }

    /**
     * Store a newly created investment plan.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:investment_plans,code',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gt:min_amount',
            'daily_profit_percent' => 'required|numeric|min:0|max:100',
            'duration_days' => 'required|integer|min:1|max:3650', // max 10 years
            'referral_percent' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        // Set audit fields
        $validated['created_by'] = auth()->user()?->id;
        $validated['updated_by'] = auth()->user()?->id;

        $plan = InvestmentPlan::create($validated);

        return $this->success($plan->load(['creator', 'updater']), 'Investment plan created successfully', 201);
    }

    /**
     * Update the specified investment plan.
     */
    public function update(Request $request, $id)
    {
        $plan = InvestmentPlan::withTrashed()->find($id);
        if (!$plan) {
            return $this->notFound('Investment plan not found');
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('investment_plans', 'code')->ignore($plan->id)],
            'name' => 'nullable|string|max:150',
            'description' => 'nullable|string',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'daily_profit_percent' => 'nullable|numeric|min:0|max:100',
            'duration_days' => 'nullable|integer|min:1|max:3650',
            'referral_percent' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        // Validate max_amount > min_amount if both are provided
        if (isset($validated['min_amount']) && isset($validated['max_amount'])) {
            if ($validated['max_amount'] <= $validated['min_amount']) {
                return $this->validationError(['max_amount' => ['Maximum amount must be greater than minimum amount']]);
            }
        }

        // Set audit field
        $validated['updated_by'] = auth()->user()?->id;

        $plan->update($validated);

        return $this->success($plan->load(['creator', 'updater']), 'Investment plan updated successfully');
    }

    /**
     * Soft delete the specified investment plan.
     */
    public function destroy($id)
    {
        $plan = InvestmentPlan::find($id);
        if (!$plan) {
            return $this->notFound('Investment plan not found');
        }

        // Check if plan has active investments
        $activeInvestments = $plan->investments()->where('status', 'active')->count();
        if ($activeInvestments > 0) {
            return $this->error('Cannot delete plan with active investments', 422);
        }

        $plan->delete();

        return $this->success(null, 'Investment plan soft-deleted successfully');
    }

    /**
     * Restore a soft-deleted investment plan.
     */
    public function restore($id)
    {
        $plan = InvestmentPlan::withTrashed()->find($id);
        if (!$plan) {
            return $this->notFound('Investment plan not found');
        }

        if (!$plan->trashed()) {
            return $this->error('Investment plan is not deleted', 400);
        }

        $plan->restore();
        $plan->updated_by = auth()->user()?->id;
        $plan->save();

        return $this->success($plan->load(['creator', 'updater']), 'Investment plan restored successfully');
    }

    /**
     * Permanently delete the specified investment plan.
     */
    public function forceDelete($id)
    {
        $plan = InvestmentPlan::withTrashed()->find($id);
        if (!$plan) {
            return $this->notFound('Investment plan not found');
        }

        // Check if plan has any investments
        $investmentsCount = $plan->investments()->count();
        if ($investmentsCount > 0) {
            return $this->error('Cannot permanently delete plan with existing investments', 422);
        }

        $plan->forceDelete();

        return $this->success(null, 'Investment plan permanently deleted');
    }

    /**
     * Get investment plan statistics.
     */
    public function stats($id)
    {
        $plan = InvestmentPlan::find($id);
        if (!$plan) {
            return $this->notFound('Investment plan not found');
        }

        $stats = $plan->getStats();

        return $this->success($stats, 'Investment plan statistics retrieved');
    }

    /**
     * Toggle the active status of the investment plan.
     */
    public function toggleStatus($id)
    {
        $plan = InvestmentPlan::find($id);
        if (!$plan) {
            return $this->notFound('Investment plan not found');
        }

        $plan->toggleStatus();

        return $this->success($plan->load(['creator', 'updater']), 'Investment plan status toggled successfully');
    }

    /**
     * Create a new version of the investment plan.
     */
    public function createVersion(Request $request, $id)
    {
        $plan = InvestmentPlan::find($id);
        if (!$plan) {
            return $this->notFound('Investment plan not found');
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'description' => 'nullable|string',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'daily_profit_percent' => 'nullable|numeric|min:0|max:100',
            'duration_days' => 'nullable|integer|min:1|max:3650',
            'referral_percent' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $newPlan = $plan->createVersion($validated);

        return $this->success($newPlan->load(['creator', 'updater']), 'New version of investment plan created successfully', 201);
    }

    /**
     * Get all active investment plans.
     */
    public function active()
    {
        $plans = InvestmentPlan::active()
            ->with(['creator', 'updater'])
            ->orderBy('min_amount')
            ->get();

        return $this->success($plans, 'Active investment plans retrieved successfully');
    }

    /**
     * Get investment plans suitable for a specific amount.
     */
    public function forAmount(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $plans = InvestmentPlan::active()
            ->forAmount($validated['amount'])
            ->with(['creator', 'updater'])
            ->orderBy('daily_profit_percent', 'desc')
            ->get();

        return $this->success($plans, 'Suitable investment plans retrieved successfully');
    }

    /**
     * Bulk update investment plans.
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:investment_plans,id',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $updateData = array_filter([
            'is_active' => $validated['is_active'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
            'updated_by' => auth()->user()?->id,
        ]);

        if (empty($updateData)) {
            return $this->validationError(['update_data' => ['At least one field must be provided for update']]);
        }

        $updated = InvestmentPlan::whereIn('id', $validated['ids'])->update($updateData);

        return $this->success(['updated_count' => $updated], 'Investment plans updated successfully');
    }

    /**
     * Export investment plans data.
     */
    public function export(Request $request)
    {
        $query = InvestmentPlan::query();

        // Apply same filters as index method
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        $plans = $query->with(['creator', 'updater'])->get();

        // Format data for export
        $exportData = $plans->map(function ($plan) {
            return [
                'ID' => $plan->id,
                'Code' => $plan->code,
                'Name' => $plan->name,
                'Description' => $plan->description,
                'Min Amount' => $plan->min_amount,
                'Max Amount' => $plan->max_amount,
                'Daily Profit %' => $plan->daily_profit_percent,
                'Duration (Days)' => $plan->duration_days,
                'Referral %' => $plan->referral_percent,
                'Total Return %' => $plan->total_return_percent,
                'Is Active' => $plan->is_active ? 'Yes' : 'No',
                'Version' => $plan->version,
                'Created By' => $plan->creator?->name ?? 'System',
                'Created At' => $plan->created_at->format('Y-m-d H:i:s'),
                'Updated At' => $plan->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->success($exportData, 'Investment plans exported successfully');
    }
}
