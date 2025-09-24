<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserInvestment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'investment_plan_id',
        'amount',
        'daily_profit_percent',
        'duration_days',
        'invested_at',
        'start_at',
        'end_at',
        'matured_at',
        'accrued_interest',
        'total_payout',
        'status',
        'referrer_id',
        'referral_commission',
        'metadata',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:8',
        'daily_profit_percent' => 'decimal:6',
        'accrued_interest' => 'decimal:8',
        'total_payout' => 'decimal:8',
        'referral_commission' => 'decimal:8',
        'invested_at' => 'datetime',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'matured_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_WITHDRAWN = 'withdrawn';

    /**
     * Get the user who made this investment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the investment plan.
     */
    public function investmentPlan(): BelongsTo
    {
        return $this->belongsTo(InvestmentPlan::class);
    }

    /**
     * Get the referrer (if any).
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * Get the user who created this record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get investments by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get active investments.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get pending investments.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get completed investments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to get investments by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get investments by plan.
     */
    public function scopeByPlan($query, int $planId)
    {
        return $query->where('investment_plan_id', $planId);
    }

    /**
     * Scope to get investments that are due for maturity.
     */
    public function scopeDueForMaturity($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('end_at', '<=', now());
    }

    /**
     * Scope to get investments that need daily interest accrual.
     */
    public function scopeForDailyAccrual($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('start_at', '<=', now())
            ->where('end_at', '>', now());
    }

    /**
     * Check if investment is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if investment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if investment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if investment is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if investment is withdrawn.
     */
    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }

    /**
     * Check if investment has matured.
     */
    public function hasMatured(): bool
    {
        return $this->end_at && $this->end_at <= now();
    }

    /**
     * Get the daily interest amount.
     */
    public function getDailyInterestAmount(): float
    {
        return $this->amount * ($this->daily_profit_percent / 100);
    }

    /**
     * Get the total expected return.
     */
    public function getTotalExpectedReturn(): float
    {
        return $this->amount * ($this->daily_profit_percent / 100) * $this->duration_days;
    }

    /**
     * Get the remaining days until maturity.
     */
    public function getRemainingDays(): int
    {
        if (!$this->end_at) {
            return 0;
        }

        $remaining = now()->diffInDays($this->end_at, false);
        return max(0, $remaining);
    }

    /**
     * Get the days elapsed since start.
     */
    public function getElapsedDays(): int
    {
        if (!$this->start_at) {
            return 0;
        }

        return now()->diffInDays($this->start_at);
    }

    /**
     * Get the progress percentage (0-100).
     */
    public function getProgressPercentage(): float
    {
        if ($this->duration_days <= 0) {
            return 0;
        }

        $elapsed = $this->getElapsedDays();
        return min(100, ($elapsed / $this->duration_days) * 100);
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2);
    }

    /**
     * Get formatted accrued interest.
     */
    public function getFormattedAccruedInterestAttribute(): string
    {
        return number_format($this->accrued_interest, 2);
    }

    /**
     * Get formatted total payout.
     */
    public function getFormattedTotalPayoutAttribute(): string
    {
        return number_format($this->total_payout, 2);
    }

    /**
     * Get formatted referral commission.
     */
    public function getFormattedReferralCommissionAttribute(): string
    {
        return number_format($this->referral_commission, 2);
    }

    /**
     * Get formatted daily profit percentage.
     */
    public function getFormattedDailyProfitPercentAttribute(): string
    {
        return number_format($this->daily_profit_percent, 2) . '%';
    }

    /**
     * Get investment summary.
     */
    public function getSummaryAttribute(): string
    {
        return "Investment #{$this->id} - {$this->investmentPlan->name} - {$this->formatted_amount}";
    }

    /**
     * Activate the investment.
     */
    public function activate(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $now = now();
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'start_at' => $now,
            'end_at' => $now->copy()->addDays($this->duration_days),
            'updated_by' => auth()->user()?->id,
        ]);

        return true;
    }

    /**
     * Complete the investment.
     */
    public function complete(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'matured_at' => now(),
            'updated_by' => auth()->user()?->id,
        ]);

        return true;
    }

    /**
     * Cancel the investment.
     */
    public function cancel(): bool
    {
        if (!in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACTIVE])) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'updated_by' => auth()->user()?->id,
        ]);

        return true;
    }

    /**
     * Withdraw the investment.
     */
    public function withdraw(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_WITHDRAWN,
            'updated_by' => auth()->user()?->id,
        ]);

        return true;
    }

    /**
     * Add accrued interest.
     */
    public function addAccruedInterest(float $amount): bool
    {
        $this->increment('accrued_interest', $amount);
        return true;
    }

    /**
     * Add total payout.
     */
    public function addTotalPayout(float $amount): bool
    {
        $this->increment('total_payout', $amount);
        return true;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($investment) {
            if (!$investment->invested_at) {
                $investment->invested_at = now();
            }
        });
    }
}
