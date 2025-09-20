<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SettingService
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
     * Get a setting value by key with caching.
     *
     * @param string $key
     * @param mixed $default
     * @param int|null $ttl
     * @return mixed
     */
    public function get(string $key, $default = null, ?int $ttl = null)
    {
        $cacheKey = $this->cachePrefix . $key;
        $ttl = $ttl ?? $this->cacheTtl;

        $item = Cache::remember($cacheKey, $ttl, function () use ($key) {
            return SystemSetting::where('key', $key)->first();
        });

        if (!$item) {
            return $default;
        }

        return $item->value['value'] ?? $item->value ?? $default;
    }

    /**
     * Get all settings as key-value pairs with caching.
     *
     * @param int|null $ttl
     * @return array
     */
    public function all(?int $ttl = null): array
    {
        $cacheKey = $this->cachePrefix . 'all';
        $ttl = $ttl ?? $this->cacheTtl;

        return Cache::remember($cacheKey, $ttl, function () {
            return SystemSetting::all()
                ->pluck('value', 'key')
                ->map(function ($value) {
                    return is_array($value) && isset($value['value']) ? $value['value'] : $value;
                })
                ->toArray();
        });
    }

    /**
     * Set a setting value by key.
     *
     * @param string $key
     * @param mixed $value
     * @param string|null $description
     * @param int|null $updatedBy
     * @return SystemSetting
     */
    public function set(string $key, $value, ?string $description = null, ?int $updatedBy = null): SystemSetting
    {
        $setting = SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? $value : ['value' => $value],
                'description' => $description,
                'updated_by' => $updatedBy,
            ]
        );

        // Update cache
        $this->flush($key);

        return $setting;
    }

    /**
     * Check if a setting exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get a boolean setting value.
     *
     * @param string $key
     * @param bool $default
     * @return bool
     */
    public function getBoolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get an integer setting value.
     *
     * @param string $key
     * @param int $default
     * @return int
     */
    public function getInteger(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return (int) $value;
    }

    /**
     * Get a float setting value.
     *
     * @param string $key
     * @param float $default
     * @return float
     */
    public function getFloat(string $key, float $default = 0.0): float
    {
        $value = $this->get($key, $default);
        return (float) $value;
    }

    /**
     * Get a string setting value.
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return (string) $value;
    }

    /**
     * Get an array setting value.
     *
     * @param string $key
     * @param array $default
     * @return array
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * Get MLM-specific settings.
     *
     * @return array
     */
    public function getMlmSettings(): array
    {
        return [
            'max_direct_children' => $this->getInteger('max_direct_children', 4),
            'club_matrix_levels' => $this->getInteger('club_matrix_levels', 10),
            'autopool_levels' => $this->getInteger('autopool_levels', 8),
            'referral_enabled' => $this->getBoolean('referral_enabled', true),
            'referral_bonus_percentage' => $this->getFloat('referral_bonus_percentage', 10.0),
            'matching_bonus_percentage' => $this->getFloat('matching_bonus_percentage', 5.0),
            'leadership_bonus_threshold' => $this->getFloat('leadership_bonus_threshold', 1000.0),
        ];
    }

    /**
     * Get financial settings.
     *
     * @return array
     */
    public function getFinancialSettings(): array
    {
        return [
            'default_currency' => $this->getString('default_currency', 'USD'),
            'min_package_amount' => $this->getFloat('min_package_amount', 100.0),
            'max_package_amount' => $this->getFloat('max_package_amount', 10000.0),
            'payout_processing_enabled' => $this->getBoolean('payout_processing_enabled', true),
            'payout_minimum_amount' => $this->getFloat('payout_minimum_amount', 50.0),
            'payout_processing_fee' => $this->getFloat('payout_processing_fee', 2.5),
            'income_calculation_precision' => $this->getInteger('income_calculation_precision', 8),
        ];
    }

    /**
     * Get security settings.
     *
     * @return array
     */
    public function getSecuritySettings(): array
    {
        return [
            'max_login_attempts' => $this->getInteger('max_login_attempts', 5),
            'session_timeout' => $this->getInteger('session_timeout', 3600),
            'audit_log_retention_days' => $this->getInteger('audit_log_retention_days', 365),
            'email_verification_required' => $this->getBoolean('email_verification_required', true),
            'phone_verification_required' => $this->getBoolean('phone_verification_required', false),
        ];
    }

    /**
     * Get platform settings.
     *
     * @return array
     */
    public function getPlatformSettings(): array
    {
        return [
            'site_name' => $this->getString('site_name', 'Fylo MLM Platform'),
            'site_description' => $this->getString('site_description', 'Advanced MLM Platform'),
            'admin_email' => $this->getString('admin_email', 'admin@fylomlm.com'),
            'support_email' => $this->getString('support_email', 'support@fylomlm.com'),
            'maintenance_mode' => $this->getBoolean('maintenance_mode', false),
            'user_registration_enabled' => $this->getBoolean('user_registration_enabled', true),
        ];
    }

    /**
     * Get cache settings.
     *
     * @return array
     */
    public function getCacheSettings(): array
    {
        return [
            'income_config_cache_ttl' => $this->getInteger('income_config_cache_ttl', 3600),
            'backup_frequency' => $this->getString('backup_frequency', 'daily'),
        ];
    }

    /**
     * Get payout settings.
     *
     * @return array
     */
    public function getPayoutSettings(): array
    {
        return [
            'commission_payout_schedule' => $this->getString('commission_payout_schedule', 'daily'),
            'auto_approve_packages' => $this->getBoolean('auto_approve_packages', false),
        ];
    }

    /**
     * Clear cache for a specific setting.
     *
     * @param string $key
     * @return void
     */
    public function flush(string $key): void
    {
        $cacheKey = $this->cachePrefix . $key;
        Cache::forget($cacheKey);
    }

    /**
     * Clear all settings cache.
     *
     * @return void
     */
    public function flushAll(): void
    {
        SystemSetting::clearAllCache();
        Cache::forget($this->cachePrefix . 'all');
    }

    /**
     * Get setting with full metadata.
     *
     * @param string $key
     * @return SystemSetting|null
     */
    public function getSetting(string $key): ?SystemSetting
    {
        $cacheKey = $this->cachePrefix . 'full_' . $key;

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($key) {
            return SystemSetting::with(['updater'])->where('key', $key)->first();
        });
    }

    /**
     * Bulk update settings.
     *
     * @param array $settings
     * @param int|null $updatedBy
     * @return array
     */
    public function bulkUpdate(array $settings, ?int $updatedBy = null): array
    {
        $updated = [];
        $errors = [];

        foreach ($settings as $key => $value) {
            try {
                $setting = $this->set($key, $value, null, $updatedBy);
                $updated[] = $setting;
            } catch (\Exception $e) {
                $errors[] = "Failed to update setting '{$key}': " . $e->getMessage();
            }
        }

        return [
            'updated' => $updated,
            'errors' => $errors,
            'total_updated' => count($updated),
            'total_errors' => count($errors)
        ];
    }

    /**
     * Check if maintenance mode is enabled.
     *
     * @return bool
     */
    public function isMaintenanceMode(): bool
    {
        return $this->getBoolean('maintenance_mode', false);
    }

    /**
     * Get maintenance mode message.
     *
     * @return string
     */
    public function getMaintenanceMessage(): string
    {
        $setting = $this->getSetting('maintenance_mode');
        if (!$setting || !is_array($setting->value)) {
            return 'Site is under maintenance. Please try again later.';
        }

        return $setting->value['message'] ?? 'Site is under maintenance. Please try again later.';
    }

    /**
     * Check if user registration is enabled.
     *
     * @return bool
     */
    public function isUserRegistrationEnabled(): bool
    {
        return $this->getBoolean('user_registration_enabled', true);
    }

    /**
     * Check if referral system is enabled.
     *
     * @return bool
     */
    public function isReferralEnabled(): bool
    {
        return $this->getBoolean('referral_enabled', true);
    }

    /**
     * Check if payout processing is enabled.
     *
     * @return bool
     */
    public function isPayoutProcessingEnabled(): bool
    {
        return $this->getBoolean('payout_processing_enabled', true);
    }
}
