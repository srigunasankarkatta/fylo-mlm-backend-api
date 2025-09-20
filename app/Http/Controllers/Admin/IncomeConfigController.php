<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Models\IncomeConfig;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncomeConfigController extends ApiController
{
    /**
     * Display a listing of income configurations.
     */
    public function index(Request $request)
    {
        $query = IncomeConfig::query();

        // Filter by income type
        if ($request->filled('income_type')) {
            $query->where('income_type', $request->income_type);
        }

        // Filter by package
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        // Filter by level
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        // Filter by sub level
        if ($request->filled('sub_level')) {
            $query->where('sub_level', $request->sub_level);
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by version
        if ($request->filled('version')) {
            $query->where('version', $request->version);
        }

        // Search functionality
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('income_type', 'like', "%{$q}%");
            });
        }

        // Date range filtering
        if ($request->filled('effective_from')) {
            $query->where('effective_from', '>=', $request->effective_from);
        }
        if ($request->filled('effective_to')) {
            $query->where('effective_to', '<=', $request->effective_to);
        }

        // Include relationships
        $query->with(['package', 'creator']);

        // Order by
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $configs = $query->paginate($request->get('per_page', 20));

        return $this->paginated($configs, 'Income configurations retrieved successfully');
    }

    /**
     * Display the specified income configuration.
     */
    public function show($id)
    {
        $config = IncomeConfig::withTrashed()
            ->with(['package', 'creator'])
            ->find($id);

        if (!$config) {
            return $this->notFound('Income configuration not found');
        }

        return $this->success($config, 'Income configuration retrieved successfully');
    }

    /**
     * Store a newly created income configuration.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'income_type' => 'required|in:level,fasttrack,club,autopool,other',
            'package_id' => 'nullable|integer|exists:packages,id',
            'level' => 'nullable|integer|min:1|max:10',
            'sub_level' => 'nullable|integer|min:1|max:8',
            'percentage' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'metadata' => 'nullable|array'
        ]);

        // Set creator and version
        $validated['created_by'] = auth('api')->id();
        $validated['version'] = 1;

        $config = IncomeConfig::create($validated);

        return $this->success($config->load(['package', 'creator']), 'Income configuration created successfully', 201);
    }

    /**
     * Update the specified income configuration.
     */
    public function update(Request $request, $id)
    {
        $config = IncomeConfig::withTrashed()->find($id);
        if (!$config) {
            return $this->notFound('Income configuration not found');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'income_type' => 'sometimes|in:level,fasttrack,club,autopool,other',
            'package_id' => 'nullable|integer|exists:packages,id',
            'level' => 'nullable|integer|min:1|max:10',
            'sub_level' => 'nullable|integer|min:1|max:8',
            'percentage' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'metadata' => 'nullable|array'
        ]);

        $config->update($validated);

        return $this->success($config->load(['package', 'creator']), 'Income configuration updated successfully');
    }

    /**
     * Soft delete the specified income configuration.
     */
    public function destroy($id)
    {
        $config = IncomeConfig::find($id);
        if (!$config) {
            return $this->notFound('Income configuration not found');
        }

        $config->delete();

        return $this->success(null, 'Income configuration soft-deleted successfully');
    }

    /**
     * Restore a soft-deleted income configuration.
     */
    public function restore($id)
    {
        $config = IncomeConfig::withTrashed()->find($id);
        if (!$config) {
            return $this->notFound('Income configuration not found');
        }

        if (!$config->trashed()) {
            return $this->error('Income configuration is not deleted', 400);
        }

        $config->restore();

        return $this->success($config->load(['package', 'creator']), 'Income configuration restored successfully');
    }

    /**
     * Permanently delete the specified income configuration.
     */
    public function forceDelete($id)
    {
        $config = IncomeConfig::withTrashed()->find($id);
        if (!$config) {
            return $this->notFound('Income configuration not found');
        }

        $config->forceDelete();

        return $this->success(null, 'Income configuration permanently deleted');
    }

    /**
     * Get income configuration statistics.
     */
    public function stats($id)
    {
        $config = IncomeConfig::find($id);
        if (!$config) {
            return $this->notFound('Income configuration not found');
        }

        $stats = [
            'config' => $config,
            'total_income_records' => $config->incomeRecords()->count(),
            'pending_income_records' => $config->incomeRecords()->pending()->count(),
            'paid_income_records' => $config->incomeRecords()->paid()->count(),
            'total_income_amount' => $config->incomeRecords()->sum('amount'),
            'pending_income_amount' => $config->incomeRecords()->pending()->sum('amount'),
            'paid_income_amount' => $config->incomeRecords()->paid()->sum('amount'),
            'recent_income_records' => $config->incomeRecords()
                ->with('user')
                ->latest()
                ->limit(10)
                ->get(),
        ];

        return $this->success($stats, 'Income configuration statistics retrieved');
    }

    /**
     * Toggle income configuration active status.
     */
    public function toggleStatus($id)
    {
        $config = IncomeConfig::find($id);
        if (!$config) {
            return $this->notFound('Income configuration not found');
        }

        $config->update(['is_active' => !$config->is_active]);

        return $this->success($config, 'Income configuration status updated successfully');
    }

    /**
     * Create a new version of the income configuration.
     */
    public function createVersion(Request $request, $id)
    {
        $config = IncomeConfig::find($id);
        if (!$config) {
            return $this->notFound('Income configuration not found');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'percentage' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'metadata' => 'nullable|array'
        ]);

        // Create new version
        $newConfig = $config->replicate();
        $newConfig->fill($validated);
        $newConfig->version = $config->getNextVersion();
        $newConfig->created_by = auth('api')->id();
        $newConfig->save();

        // Deactivate old version
        $config->deactivate();

        return $this->success($newConfig->load(['package', 'creator']), 'New version created successfully', 201);
    }

    /**
     * Get effective income configurations for a specific criteria.
     */
    public function effective(Request $request)
    {
        $validated = $request->validate([
            'income_type' => 'required|in:level,fasttrack,club,autopool,other',
            'package_id' => 'nullable|integer|exists:packages,id',
            'level' => 'nullable|integer|min:1|max:10',
            'sub_level' => 'nullable|integer|min:1|max:8',
            'date' => 'nullable|date'
        ]);

        $configs = IncomeConfig::getEffectiveConfigs(
            $validated['income_type'],
            $validated['package_id'] ?? null,
            $validated['level'] ?? null,
            $validated['sub_level'] ?? null,
            $validated['date'] ?? now()
        );

        return $this->success($configs, 'Effective income configurations retrieved');
    }

    /**
     * Get income configuration types and their counts.
     */
    public function types()
    {
        $types = IncomeConfig::getIncomeTypes();

        return $this->success($types, 'Income configuration types retrieved');
    }
}
