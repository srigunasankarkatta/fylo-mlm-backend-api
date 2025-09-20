<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'description',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Get the admin user who last updated this setting.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include settings by key.
     */
    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    /**
     * Get setting value by key.
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = static::byKey($key)->first();

        if (!$setting) {
            return $default;
        }

        return $setting->value['value'] ?? $setting->value ?? $default;
    }

    /**
     * Set setting value by key.
     */
    public static function setValue(string $key, $value, string $description = null, int $updatedBy = null): SystemSetting
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? $value : ['value' => $value],
                'description' => $description,
                'updated_by' => $updatedBy,
            ]
        );

        return $setting;
    }

    /**
     * Get all settings as key-value pairs.
     */
    public static function getAllAsArray(): array
    {
        return static::all()
            ->pluck('value', 'key')
            ->map(function ($value) {
                return is_array($value) && isset($value['value']) ? $value['value'] : $value;
            })
            ->toArray();
    }

    /**
     * Check if a setting exists.
     */
    public static function exists(string $key): bool
    {
        return static::byKey($key)->exists();
    }

    /**
     * Get setting with cache.
     */
    public static function getCached(string $key, $default = null, int $ttl = 3600)
    {
        $cacheKey = 'system_setting_' . $key;

        return cache()->remember($cacheKey, $ttl, function () use ($key, $default) {
            return static::getValue($key, $default);
        });
    }

    /**
     * Clear setting cache.
     */
    public static function clearCache(string $key): void
    {
        $cacheKey = 'system_setting_' . $key;
        cache()->forget($cacheKey);
    }

    /**
     * Clear all settings cache.
     */
    public static function clearAllCache(): void
    {
        $settings = static::all();
        foreach ($settings as $setting) {
            static::clearCache($setting->key);
        }
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Clear cache when setting is updated or deleted
        static::updated(function ($setting) {
            static::clearCache($setting->key);
        });

        static::deleted(function ($setting) {
            static::clearCache($setting->key);
        });
    }
}
