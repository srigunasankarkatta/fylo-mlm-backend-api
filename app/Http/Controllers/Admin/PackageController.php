<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use App\Models\Package;
use Illuminate\Validation\Rule;

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
}
