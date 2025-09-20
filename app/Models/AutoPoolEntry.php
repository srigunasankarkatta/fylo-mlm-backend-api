<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AutoPoolEntry extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'package_id',
        'pool_level',
        'pool_sub_level',
        'placed_at',
        'status',
        'allocated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'placed_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_PAID_OUT = 'paid_out';

    /**
     * Get the user who owns this pool entry.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package associated with this pool entry.
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the user who allocated this entry.
     */
    public function allocator()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    /**
     * Scope a query to only include entries for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include entries by package.
     */
    public function scopeByPackage($query, int $packageId)
    {
        return $query->where('package_id', $packageId);
    }

    /**
     * Scope a query to only include entries by pool level.
     */
    public function scopeByPoolLevel($query, int $level)
    {
        return $query->where('pool_level', $level);
    }

    /**
     * Scope a query to only include entries by pool sub level.
     */
    public function scopeByPoolSubLevel($query, int $subLevel)
    {
        return $query->where('pool_sub_level', $subLevel);
    }

    /**
     * Scope a query to only include entries by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include active entries.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include completed entries.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to only include paid out entries.
     */
    public function scopePaidOut($query)
    {
        return $query->where('status', self::STATUS_PAID_OUT);
    }

    /**
     * Scope a query to only include entries for a specific pool level and sub level.
     */
    public function scopeForPoolLevel($query, int $level, int $subLevel)
    {
        return $query->where('pool_level', $level)
            ->where('pool_sub_level', $subLevel);
    }

    /**
     * Scope a query to only include entries within date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('placed_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include entries placed after a specific date.
     */
    public function scopePlacedAfter($query, $date)
    {
        return $query->where('placed_at', '>', $date);
    }

    /**
     * Scope a query to only include entries placed before a specific date.
     */
    public function scopePlacedBefore($query, $date)
    {
        return $query->where('placed_at', '<', $date);
    }

    /**
     * Create a new auto pool entry.
     */
    public static function createEntry(array $data): AutoPoolEntry
    {
        return static::create($data);
    }

    /**
     * Create an active pool entry.
     */
    public static function createActiveEntry(
        int $userId,
        int $packageId,
        int $poolLevel,
        int $poolSubLevel,
        ?int $allocatedBy = null
    ): AutoPoolEntry {
        return static::createEntry([
            'user_id' => $userId,
            'package_id' => $packageId,
            'pool_level' => $poolLevel,
            'pool_sub_level' => $poolSubLevel,
            'placed_at' => now(),
            'status' => self::STATUS_ACTIVE,
            'allocated_by' => $allocatedBy,
        ]);
    }

    /**
     * Mark this entry as completed.
     */
    public function markAsCompleted(): bool
    {
        return $this->update(['status' => self::STATUS_COMPLETED]);
    }

    /**
     * Mark this entry as paid out.
     */
    public function markAsPaidOut(): bool
    {
        return $this->update(['status' => self::STATUS_PAID_OUT]);
    }

    /**
     * Check if this entry is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if this entry is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if this entry is paid out.
     */
    public function isPaidOut(): bool
    {
        return $this->status === self::STATUS_PAID_OUT;
    }

    /**
     * Get pool level name.
     */
    public function getPoolLevelNameAttribute(): string
    {
        return "Pool Level {$this->pool_level}";
    }

    /**
     * Get pool sub level name.
     */
    public function getPoolSubLevelNameAttribute(): string
    {
        return "Sub Level {$this->pool_sub_level}";
    }

    /**
     * Get pool position name.
     */
    public function getPoolPositionNameAttribute(): string
    {
        return "Level {$this->pool_level}.{$this->pool_sub_level}";
    }

    /**
     * Get entry summary.
     */
    public function getSummaryAttribute(): string
    {
        return "User #{$this->user_id} - {$this->package->name} - {$this->pool_position_name} - {$this->status}";
    }

    /**
     * Get all statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_COMPLETED,
            self::STATUS_PAID_OUT,
        ];
    }

    /**
     * Get entries count for a specific pool level and sub level.
     */
    public static function getCountForPoolLevel(int $level, int $subLevel): int
    {
        return static::forPoolLevel($level, $subLevel)->count();
    }

    /**
     * Get active entries count for a specific pool level and sub level.
     */
    public static function getActiveCountForPoolLevel(int $level, int $subLevel): int
    {
        return static::forPoolLevel($level, $subLevel)->active()->count();
    }

    /**
     * Get entries for a specific pool level and sub level.
     */
    public static function getEntriesForPoolLevel(int $level, int $subLevel)
    {
        return static::forPoolLevel($level, $subLevel)->orderBy('placed_at')->get();
    }

    /**
     * Get active entries for a specific pool level and sub level.
     */
    public static function getActiveEntriesForPoolLevel(int $level, int $subLevel)
    {
        return static::forPoolLevel($level, $subLevel)->active()->orderBy('placed_at')->get();
    }

    /**
     * Get next available position in a pool level and sub level.
     */
    public static function getNextAvailablePosition(int $level, int $subLevel): ?int
    {
        $lastEntry = static::forPoolLevel($level, $subLevel)
            ->orderBy('id', 'desc')
            ->first();

        return $lastEntry ? $lastEntry->id + 1 : 1;
    }

    /**
     * Get pool statistics for a specific level and sub level.
     */
    public static function getPoolStats(int $level, int $subLevel): array
    {
        $totalEntries = static::getCountForPoolLevel($level, $subLevel);
        $activeEntries = static::getActiveCountForPoolLevel($level, $subLevel);
        $completedEntries = static::forPoolLevel($level, $subLevel)->completed()->count();
        $paidOutEntries = static::forPoolLevel($level, $subLevel)->paidOut()->count();

        return [
            'total_entries' => $totalEntries,
            'active_entries' => $activeEntries,
            'completed_entries' => $completedEntries,
            'paid_out_entries' => $paidOutEntries,
            'completion_rate' => $totalEntries > 0 ? ($completedEntries / $totalEntries) * 100 : 0,
            'payout_rate' => $totalEntries > 0 ? ($paidOutEntries / $totalEntries) * 100 : 0,
        ];
    }

    /**
     * Get user's pool entries statistics.
     */
    public static function getUserPoolStats(int $userId): array
    {
        $totalEntries = static::forUser($userId)->count();
        $activeEntries = static::forUser($userId)->active()->count();
        $completedEntries = static::forUser($userId)->completed()->count();
        $paidOutEntries = static::forUser($userId)->paidOut()->count();

        $byLevel = [];
        for ($level = 1; $level <= 10; $level++) {
            $byLevel[$level] = static::forUser($userId)->byPoolLevel($level)->count();
        }

        return [
            'total_entries' => $totalEntries,
            'active_entries' => $activeEntries,
            'completed_entries' => $completedEntries,
            'paid_out_entries' => $paidOutEntries,
            'by_level' => $byLevel,
        ];
    }

    /**
     * Get system-wide pool statistics.
     */
    public static function getSystemPoolStats(): array
    {
        $totalEntries = static::count();
        $activeEntries = static::active()->count();
        $completedEntries = static::completed()->count();
        $paidOutEntries = static::paidOut()->count();

        $byLevel = [];
        for ($level = 1; $level <= 10; $level++) {
            $byLevel[$level] = static::byPoolLevel($level)->count();
        }

        return [
            'total_entries' => $totalEntries,
            'active_entries' => $activeEntries,
            'completed_entries' => $completedEntries,
            'paid_out_entries' => $paidOutEntries,
            'by_level' => $byLevel,
        ];
    }

    /**
     * Process pool entries (mark as completed or paid out).
     */
    public static function processPoolEntries(array $entryIds, string $newStatus): int
    {
        if (!in_array($newStatus, [self::STATUS_COMPLETED, self::STATUS_PAID_OUT])) {
            return 0;
        }

        return static::whereIn('id', $entryIds)
            ->active()
            ->update(['status' => $newStatus]);
    }

    /**
     * Get pool entries ready for payout.
     */
    public static function getEntriesReadyForPayout(int $level, int $subLevel, int $requiredCount = 8)
    {
        return static::forPoolLevel($level, $subLevel)
            ->active()
            ->orderBy('placed_at')
            ->limit($requiredCount)
            ->get();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($entry) {
            if (empty($entry->placed_at)) {
                $entry->placed_at = now();
            }
            if (empty($entry->status)) {
                $entry->status = self::STATUS_ACTIVE;
            }
        });
    }
}
