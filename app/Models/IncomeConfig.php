<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class IncomeConfig extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'income_type',
        'package_id',
        'level',
        'sub_level',
        'percentage',
        'is_active',
        'version',
        'effective_from',
        'effective_to',
        'metadata',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'percentage' => 'decimal:10',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Income types constants.
     */
    const TYPE_LEVEL = 'level';
    const TYPE_FASTTRACK = 'fasttrack';
    const TYPE_CLUB = 'club';
    const TYPE_AUTOPOOL = 'autopool';
    const TYPE_OTHER = 'other';

    /**
     * Get the package associated with this config.
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the user who created this config.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the income records for this config.
     */
    public function incomeRecords()
    {
        return $this->hasMany(IncomeRecord::class);
    }

    /**
     * Scope a query to only include active configs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include configs by income type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('income_type', $type);
    }

    /**
     * Scope a query to only include configs by package.
     */
    public function scopeByPackage($query, int $packageId)
    {
        return $query->where('package_id', $packageId);
    }

    /**
     * Scope a query to only include global configs.
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('package_id');
    }

    /**
     * Scope a query to only include configs by level.
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope a query to only include configs by sub level.
     */
    public function scopeBySubLevel($query, int $subLevel)
    {
        return $query->where('sub_level', $subLevel);
    }

    /**
     * Scope a query to only include configs by version.
     */
    public function scopeByVersion($query, int $version)
    {
        return $query->where('version', $version);
    }

    /**
     * Scope a query to only include configs effective at a specific date.
     */
    public function scopeEffectiveAt($query, $date = null)
    {
        $date = $date ? Carbon::parse($date) : now();

        return $query->where(function ($q) use ($date) {
            $q->whereNull('effective_from')
                ->orWhere('effective_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('effective_to')
                ->orWhere('effective_to', '>=', $date);
        });
    }

    /**
     * Scope a query to only include configs for a specific package and level.
     */
    public function scopeForPackageLevel($query, int $packageId, int $level)
    {
        return $query->where('package_id', $packageId)
            ->where('level', $level);
    }

    /**
     * Scope a query to only include configs for a specific package, level, and sub level.
     */
    public function scopeForPackageLevelSubLevel($query, int $packageId, int $level, int $subLevel)
    {
        return $query->where('package_id', $packageId)
            ->where('level', $level)
            ->where('sub_level', $subLevel);
    }

    /**
     * Get the percentage as a percentage value (e.g., 0.005 => 0.5%).
     */
    public function getPercentageValueAttribute(): float
    {
        return $this->percentage * 100;
    }

    /**
     * Get formatted percentage.
     */
    public function getFormattedPercentageAttribute(): string
    {
        return number_format($this->percentage_value, 2) . '%';
    }

    /**
     * Get config summary.
     */
    public function getSummaryAttribute(): string
    {
        $package = $this->package ? "Package #{$this->package_id}" : 'Global';
        $level = $this->level ? "Level {$this->level}" : '';
        $subLevel = $this->sub_level ? "Sub {$this->sub_level}" : '';

        return "{$this->name} - {$package} {$level} {$subLevel} - {$this->formatted_percentage}";
    }

    /**
     * Check if config is currently effective.
     */
    public function isEffective($date = null): bool
    {
        $date = $date ? Carbon::parse($date) : now();

        $fromEffective = !$this->effective_from || $this->effective_from <= $date;
        $toEffective = !$this->effective_to || $this->effective_to >= $date;

        return $fromEffective && $toEffective;
    }

    /**
     * Check if config is global (not package-specific).
     */
    public function isGlobal(): bool
    {
        return $this->package_id === null;
    }

    /**
     * Check if config is package-specific.
     */
    public function isPackageSpecific(): bool
    {
        return $this->package_id !== null;
    }

    /**
     * Get the next version number for this config.
     */
    public function getNextVersion(): int
    {
        $maxVersion = static::where('name', $this->name)
            ->where('income_type', $this->income_type)
            ->where('package_id', $this->package_id)
            ->where('level', $this->level)
            ->where('sub_level', $this->sub_level)
            ->max('version');

        return ($maxVersion ?? 0) + 1;
    }

    /**
     * Create a new version of this config.
     */
    public function createNewVersion(array $data): IncomeConfig
    {
        $newConfig = $this->replicate();
        $newConfig->fill($data);
        $newConfig->version = $this->getNextVersion();
        $newConfig->created_at = now();
        $newConfig->save();

        return $newConfig;
    }

    /**
     * Deactivate this config.
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Activate this config.
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Get effective config for specific criteria.
     */
    public static function getEffectiveConfig(
        string $type,
        ?int $packageId = null,
        ?int $level = null,
        ?int $subLevel = null,
        $date = null
    ): ?IncomeConfig {
        $query = static::active()
            ->byType($type)
            ->effectiveAt($date);

        if ($packageId !== null) {
            $query->byPackage($packageId);
        } else {
            $query->global();
        }

        if ($level !== null) {
            $query->byLevel($level);
        }

        if ($subLevel !== null) {
            $query->bySubLevel($subLevel);
        }

        return $query->orderBy('version', 'desc')->first();
    }

    /**
     * Get all effective configs for a specific type.
     */
    public static function getEffectiveConfigs(
        string $type,
        ?int $packageId = null,
        $date = null
    ): \Illuminate\Database\Eloquent\Collection {
        $query = static::active()
            ->byType($type)
            ->effectiveAt($date);

        if ($packageId !== null) {
            $query->byPackage($packageId);
        } else {
            $query->global();
        }

        return $query->orderBy('level')->orderBy('sub_level')->get();
    }

    /**
     * Calculate income based on config.
     */
    public function calculateIncome(float $baseAmount): float
    {
        return $baseAmount * $this->percentage;
    }

    /**
     * Get all income types.
     */
    public static function getIncomeTypes(): array
    {
        return [
            self::TYPE_LEVEL,
            self::TYPE_FASTTRACK,
            self::TYPE_CLUB,
            self::TYPE_AUTOPOOL,
            self::TYPE_OTHER,
        ];
    }

    /**
     * Get config statistics.
     */
    public static function getConfigStats(): array
    {
        $totalConfigs = static::count();
        $activeConfigs = static::active()->count();
        $inactiveConfigs = static::where('is_active', false)->count();
        $globalConfigs = static::global()->count();
        $packageConfigs = static::whereNotNull('package_id')->count();

        return [
            'total_configs' => $totalConfigs,
            'active_configs' => $activeConfigs,
            'inactive_configs' => $inactiveConfigs,
            'global_configs' => $globalConfigs,
            'package_configs' => $packageConfigs,
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($config) {
            if (empty($config->version)) {
                $config->version = 1;
            }
        });
    }
}
