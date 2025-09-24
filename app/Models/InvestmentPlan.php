<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentPlan extends Model
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
        'description',
        'min_amount',
        'max_amount',
        'daily_profit_percent',
        'duration_days',
        'referral_percent',
        'is_active',
        'version',
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
        'min_amount' => 'decimal:8',
        'max_amount' => 'decimal:8',
        'daily_profit_percent' => 'decimal:6',
        'referral_percent' => 'decimal:6',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plan) {
            if (empty($plan->code)) {
                $plan->code = $plan->generateUniqueCode();
            }
        });
    }

    /**
     * Generate a unique plan code.
     */
    public function generateUniqueCode(): string
    {
        do {
            $code = 'PLAN-' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get the user who created this plan.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this plan.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all investments using this plan.
     * Note: Investment model will be created later
     */
    public function investments(): HasMany
    {
        // return $this->hasMany(Investment::class);
        // Placeholder until Investment model is created
        return $this->hasMany(User::class, 'id', 'id'); // Temporary placeholder
    }

    /**
     * Scope to get only active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get plans within amount range.
     */
    public function scopeForAmount($query, $amount)
    {
        return $query->where('min_amount', '<=', $amount)
            ->where(function ($q) use ($amount) {
                $q->whereNull('max_amount')
                    ->orWhere('max_amount', '>=', $amount);
            });
    }

    /**
     * Get the total return percentage for the entire duration.
     */
    public function getTotalReturnPercentAttribute(): float
    {
        return $this->daily_profit_percent * $this->duration_days;
    }

    /**
     * Get the total return amount for a given investment.
     */
    public function getTotalReturnAmount(float $investmentAmount): float
    {
        return $investmentAmount * ($this->total_return_percent / 100);
    }

    /**
     * Get the daily return amount for a given investment.
     */
    public function getDailyReturnAmount(float $investmentAmount): float
    {
        return $investmentAmount * ($this->daily_profit_percent / 100);
    }

    /**
     * Get the referral commission amount for a given investment.
     */
    public function getReferralCommissionAmount(float $investmentAmount): float
    {
        return $investmentAmount * ($this->referral_percent / 100);
    }

    /**
     * Check if the plan is available for a given amount.
     */
    public function isAvailableForAmount(float $amount): bool
    {
        if ($amount < $this->min_amount) {
            return false;
        }

        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }

        return $this->is_active;
    }

    /**
     * Get plan statistics.
     */
    public function getStats(): array
    {
        $totalInvestments = $this->investments()->count();
        $totalAmount = $this->investments()->sum('amount');
        $activeInvestments = $this->investments()->where('status', 'active')->count();
        $completedInvestments = $this->investments()->where('status', 'completed')->count();

        return [
            'total_investments' => $totalInvestments,
            'total_amount' => $totalAmount,
            'active_investments' => $activeInvestments,
            'completed_investments' => $completedInvestments,
            'average_investment' => $totalInvestments > 0 ? $totalAmount / $totalInvestments : 0,
        ];
    }

    /**
     * Create a new version of this plan.
     */
    public function createVersion(array $newData = []): self
    {
        $newPlan = $this->replicate();
        $newPlan->version = $this->version + 1;
        $newPlan->created_by = auth()->user()?->id;
        $newPlan->updated_by = auth()->user()?->id;

        // Update with new data
        foreach ($newData as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $newPlan->$key = $value;
            }
        }

        $newPlan->save();
        return $newPlan;
    }

    /**
     * Toggle the active status of the plan.
     */
    public function toggleStatus(): bool
    {
        $this->is_active = !$this->is_active;
        $this->updated_by = auth()->user()?->id;
        return $this->save();
    }
}
