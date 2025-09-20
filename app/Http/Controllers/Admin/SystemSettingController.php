<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SystemSettingController extends ApiController
{
    /**
     * Cache key prefix for system settings.
     */
    protected $cachePrefix = 'system_setting_';

    /**
     * Cache TTL in seconds (1 hour).
     */
    protected $cacheTtl = 3600;

    /**
     * Display a listing of system settings.
     */
    public function index(Request $request)
    {
        $query = SystemSetting::with(['updater']);

        // Search functionality
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($builder) use ($q) {
                $builder->where('key', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        // Filter by updated_by
        if ($request->filled('updated_by')) {
            $query->where('updated_by', $request->updated_by);
        }

        // Order by
        $orderBy = $request->get('order_by', 'key');
        $orderDirection = $request->get('order_direction', 'asc');
        $query->orderBy($orderBy, $orderDirection);

        $settings = $query->paginate($request->get('per_page', 20));

        return $this->paginated($settings, 'System settings retrieved successfully');
    }

    /**
     * Display the specified system setting.
     */
    public function show($id)
    {
        $setting = SystemSetting::withTrashed()
            ->with(['updater'])
            ->find($id);

        if (!$setting) {
            return $this->notFound('System setting not found');
        }

        return $this->success($setting, 'System setting retrieved successfully');
    }

    /**
     * Display system setting by key (with caching).
     */
    public function showByKey($key)
    {
        $cacheKey = $this->cachePrefix . $key;

        $setting = Cache::remember($cacheKey, $this->cacheTtl, function () use ($key) {
            return SystemSetting::with(['updater'])->where('key', $key)->first();
        });

        if (!$setting) {
            return $this->notFound('System setting not found');
        }

        return $this->success($setting, 'System setting retrieved successfully');
    }

    /**
     * Store a newly created system setting.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:100|unique:system_settings,key',
            'value' => 'nullable|array',
            'description' => 'nullable|string|max:500'
        ]);

        $validated['updated_by'] = auth('api')->id();

        $setting = SystemSetting::create($validated);

        // Cache the new setting
        Cache::put($this->cachePrefix . $setting->key, $setting, $this->cacheTtl);

        return $this->success($setting->load(['updater']), 'System setting created successfully', 201);
    }

    /**
     * Update the specified system setting.
     */
    public function update(Request $request, $id)
    {
        $setting = SystemSetting::withTrashed()->find($id);
        if (!$setting) {
            return $this->notFound('System setting not found');
        }

        $validated = $request->validate([
            'key' => ['sometimes', 'string', 'max:100', Rule::unique('system_settings', 'key')->ignore($setting->id)],
            'value' => 'nullable|array',
            'description' => 'nullable|string|max:500'
        ]);

        $validated['updated_by'] = auth('api')->id();

        $setting->update($validated);

        // Refresh cache
        Cache::put($this->cachePrefix . $setting->key, $setting->load(['updater']), $this->cacheTtl);

        return $this->success($setting->load(['updater']), 'System setting updated successfully');
    }

    /**
     * Soft delete the specified system setting.
     */
    public function destroy($id)
    {
        $setting = SystemSetting::find($id);
        if (!$setting) {
            return $this->notFound('System setting not found');
        }

        $setting->delete();

        // Remove from cache
        Cache::forget($this->cachePrefix . $setting->key);

        return $this->success(null, 'System setting soft-deleted successfully');
    }

    /**
     * Restore a soft-deleted system setting.
     */
    public function restore($id)
    {
        $setting = SystemSetting::withTrashed()->find($id);
        if (!$setting) {
            return $this->notFound('System setting not found');
        }

        if (!$setting->trashed()) {
            return $this->error('System setting is not deleted', 400);
        }

        $setting->restore();

        // Cache the restored setting
        Cache::put($this->cachePrefix . $setting->key, $setting->load(['updater']), $this->cacheTtl);

        return $this->success($setting->load(['updater']), 'System setting restored successfully');
    }

    /**
     * Permanently delete the specified system setting.
     */
    public function forceDelete($id)
    {
        $setting = SystemSetting::withTrashed()->find($id);
        if (!$setting) {
            return $this->notFound('System setting not found');
        }

        $setting->forceDelete();

        // Remove from cache
        Cache::forget($this->cachePrefix . $setting->key);

        return $this->success(null, 'System setting permanently deleted');
    }

    /**
     * Get system setting statistics.
     */
    public function stats()
    {
        $stats = [
            'total_settings' => SystemSetting::count(),
            'deleted_settings' => SystemSetting::onlyTrashed()->count(),
            'recent_updates' => SystemSetting::with(['updater'])
                ->latest()
                ->limit(10)
                ->get(),
            'by_updater' => SystemSetting::selectRaw('updated_by, COUNT(*) as count')
                ->whereNotNull('updated_by')
                ->groupBy('updated_by')
                ->with('updater')
                ->get()
                ->map(function ($item) {
                    return [
                        'updater' => $item->updater,
                        'count' => $item->count
                    ];
                }),
        ];

        return $this->success($stats, 'System settings statistics retrieved');
    }

    /**
     * Clear cache for a specific setting.
     */
    public function clearCache($id)
    {
        $setting = SystemSetting::find($id);
        if (!$setting) {
            return $this->notFound('System setting not found');
        }

        Cache::forget($this->cachePrefix . $setting->key);

        return $this->success(null, 'Cache cleared for setting: ' . $setting->key);
    }

    /**
     * Clear all settings cache.
     */
    public function clearAllCache()
    {
        SystemSetting::clearAllCache();

        return $this->success(null, 'All settings cache cleared');
    }

    /**
     * Bulk update settings.
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable|array',
            'settings.*.description' => 'nullable|string|max:500'
        ]);

        $updated = [];
        $errors = [];

        foreach ($validated['settings'] as $settingData) {
            try {
                $setting = SystemSetting::updateOrCreate(
                    ['key' => $settingData['key']],
                    [
                        'value' => $settingData['value'] ?? null,
                        'description' => $settingData['description'] ?? null,
                        'updated_by' => auth('api')->id(),
                    ]
                );

                // Update cache
                Cache::put($this->cachePrefix . $setting->key, $setting->load(['updater']), $this->cacheTtl);

                $updated[] = $setting;
            } catch (\Exception $e) {
                $errors[] = "Failed to update setting '{$settingData['key']}': " . $e->getMessage();
            }
        }

        return $this->success([
            'updated' => $updated,
            'errors' => $errors,
            'total_updated' => count($updated),
            'total_errors' => count($errors)
        ], 'Bulk update completed');
    }

    /**
     * Export settings as JSON.
     */
    public function export()
    {
        $settings = SystemSetting::all()->map(function ($setting) {
            return [
                'key' => $setting->key,
                'value' => $setting->value,
                'description' => $setting->description,
            ];
        });

        return $this->success($settings, 'Settings exported successfully');
    }

    /**
     * Import settings from JSON.
     */
    public function import(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|max:100',
            'settings.*.value' => 'nullable|array',
            'settings.*.description' => 'nullable|string|max:500',
            'overwrite' => 'boolean'
        ]);

        $imported = [];
        $skipped = [];
        $errors = [];

        foreach ($validated['settings'] as $settingData) {
            try {
                $existing = SystemSetting::where('key', $settingData['key'])->first();

                if ($existing && !$validated['overwrite']) {
                    $skipped[] = $settingData['key'];
                    continue;
                }

                $setting = SystemSetting::updateOrCreate(
                    ['key' => $settingData['key']],
                    [
                        'value' => $settingData['value'] ?? null,
                        'description' => $settingData['description'] ?? null,
                        'updated_by' => auth('api')->id(),
                    ]
                );

                // Update cache
                Cache::put($this->cachePrefix . $setting->key, $setting->load(['updater']), $this->cacheTtl);

                $imported[] = $setting;
            } catch (\Exception $e) {
                $errors[] = "Failed to import setting '{$settingData['key']}': " . $e->getMessage();
            }
        }

        return $this->success([
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_imported' => count($imported),
            'total_skipped' => count($skipped),
            'total_errors' => count($errors)
        ], 'Settings import completed');
    }
}
