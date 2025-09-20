<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'price',
        'level_number',
        'is_active',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    /**
     * Scope a query to only include active packages.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include packages by level.
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('level_number', $level);
    }

    /**
     * Scope a query to order packages by level.
     */
    public function scopeOrderedByLevel($query)
    {
        return $query->orderBy('level_number', 'asc');
    }

    /**
     * Get the users who have purchased this package.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'package_id');
    }


    /**
     * Get all user packages for this package.
     */
    public function userPackages()
    {
        return $this->hasMany(UserPackage::class);
    }

    /**
     * Get completed user packages for this package.
     */
    public function completedUserPackages()
    {
        return $this->userPackages()->completed();
    }

    /**
     * Get total revenue from user packages.
     */
    public function getUserPackageRevenueAttribute(): float
    {
        return $this->completedUserPackages()->sum('amount_paid');
    }

    /**
     * Get user package count for this package.
     */
    public function getUserPackageCountAttribute(): int
    {
        return $this->completedUserPackages()->count();
    }

    /**
     * Get income configs for this package.
     */
    public function incomeConfigs()
    {
        return $this->hasMany(IncomeConfig::class);
    }

    /**
     * Get active income configs for this package.
     */
    public function activeIncomeConfigs()
    {
        return $this->incomeConfigs()->active();
    }

    /**
     * Get income configs by type for this package.
     */
    public function getIncomeConfigsByType(string $type)
    {
        return $this->incomeConfigs()->byType($type)->active()->get();
    }

    /**
     * Get effective income config for specific criteria.
     */
    public function getEffectiveIncomeConfig(string $type, ?int $level = null, ?int $subLevel = null, $date = null): ?IncomeConfig
    {
        return IncomeConfig::getEffectiveConfig($type, $this->id, $level, $subLevel, $date);
    }

    /**
     * Check if package is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2);
    }

    /**
     * Get package level name.
     */
    public function getLevelNameAttribute(): string
    {
        return "Level {$this->level_number}";
    }

    /**
     * Get package summary.
     */
    public function getSummaryAttribute(): string
    {
        return "{$this->name} ({$this->code}) - {$this->formatted_price}";
    }
}
