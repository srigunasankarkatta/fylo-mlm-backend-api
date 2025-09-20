<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPackage extends Model
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
        'amount_paid',
        'payment_reference',
        'payment_status',
        'purchase_at',
        'assigned_level',
        'payment_meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount_paid' => 'decimal:8',
        'purchase_at' => 'datetime',
        'payment_meta' => 'array',
    ];

    /**
     * Get the user who owns this package.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package details.
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Scope a query to only include completed packages.
     */
    public function scopeCompleted($query)
    {
        return $query->where('payment_status', 'completed');
    }

    /**
     * Scope a query to only include pending packages.
     */
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Scope a query to only include failed packages.
     */
    public function scopeFailed($query)
    {
        return $query->where('payment_status', 'failed');
    }

    /**
     * Scope a query to only include packages by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include packages by package type.
     */
    public function scopeByPackage($query, int $packageId)
    {
        return $query->where('package_id', $packageId);
    }

    /**
     * Scope a query to only include packages by level.
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('assigned_level', $level);
    }

    /**
     * Scope a query to only include packages within date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('purchase_at', [$startDate, $endDate]);
    }

    /**
     * Check if package is completed.
     */
    public function isCompleted(): bool
    {
        return $this->payment_status === 'completed';
    }

    /**
     * Check if package is pending.
     */
    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Check if package failed.
     */
    public function isFailed(): bool
    {
        return $this->payment_status === 'failed';
    }

    /**
     * Mark package as completed.
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'payment_status' => 'completed',
            'purchase_at' => now(),
        ]);
    }

    /**
     * Mark package as failed.
     */
    public function markAsFailed(): bool
    {
        return $this->update([
            'payment_status' => 'failed',
        ]);
    }

    /**
     * Get formatted amount paid.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount_paid, 2);
    }

    /**
     * Get package summary.
     */
    public function getSummaryAttribute(): string
    {
        return "Package #{$this->id} - {$this->package->name} - {$this->formatted_amount}";
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($userPackage) {
            // Set assigned_level from package if not provided
            if (empty($userPackage->assigned_level) && $userPackage->package) {
                $userPackage->assigned_level = $userPackage->package->level_number;
            }
        });
    }
}
