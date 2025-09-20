<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Payout extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'wallet_id',
        'amount',
        'fee',
        'payout_method',
        'status',
        'processed_by',
        'processed_at',
        'ledger_transaction_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'payout_method' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    const STATUS_REQUESTED = 'requested';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get the user who requested this payout.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet from which funds are drawn.
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the user who processed this payout.
     */
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the ledger transaction associated with this payout.
     */
    public function ledgerTransaction()
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    /**
     * Scope a query to only include payouts for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include payouts by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include requested payouts.
     */
    public function scopeRequested($query)
    {
        return $query->where('status', self::STATUS_REQUESTED);
    }

    /**
     * Scope a query to only include processing payouts.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope a query to only include completed payouts.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to only include failed payouts.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include rejected payouts.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope a query to only include payouts by wallet.
     */
    public function scopeByWallet($query, int $walletId)
    {
        return $query->where('wallet_id', $walletId);
    }

    /**
     * Scope a query to only include payouts within date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include payouts processed after a specific date.
     */
    public function scopeProcessedAfter($query, $date)
    {
        return $query->where('processed_at', '>', $date);
    }

    /**
     * Scope a query to only include payouts processed before a specific date.
     */
    public function scopeProcessedBefore($query, $date)
    {
        return $query->where('processed_at', '<', $date);
    }

    /**
     * Scope a query to only include payouts by processor.
     */
    public function scopeByProcessor($query, int $processorId)
    {
        return $query->where('processed_by', $processorId);
    }

    /**
     * Create a new payout request.
     */
    public static function createRequest(array $data): Payout
    {
        return static::create($data);
    }

    /**
     * Create a payout request for a user.
     */
    public static function createUserRequest(
        int $userId,
        int $walletId,
        float $amount,
        float $fee = 0,
        ?array $payoutMethod = null
    ): Payout {
        return static::createRequest([
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'amount' => $amount,
            'fee' => $fee,
            'payout_method' => $payoutMethod,
            'status' => self::STATUS_REQUESTED,
        ]);
    }

    /**
     * Mark this payout as processing.
     */
    public function markAsProcessing(?int $processedBy = null): bool
    {
        return $this->update([
            'status' => self::STATUS_PROCESSING,
            'processed_by' => $processedBy,
        ]);
    }

    /**
     * Mark this payout as completed.
     */
    public function markAsCompleted(?int $processedBy = null, ?int $ledgerTransactionId = null): bool
    {
        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'processed_by' => $processedBy,
            'processed_at' => now(),
            'ledger_transaction_id' => $ledgerTransactionId,
        ]);
    }

    /**
     * Mark this payout as failed.
     */
    public function markAsFailed(?int $processedBy = null): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'processed_by' => $processedBy,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark this payout as rejected.
     */
    public function markAsRejected(?int $processedBy = null): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'processed_by' => $processedBy,
            'processed_at' => now(),
        ]);
    }

    /**
     * Check if this payout is requested.
     */
    public function isRequested(): bool
    {
        return $this->status === self::STATUS_REQUESTED;
    }

    /**
     * Check if this payout is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if this payout is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if this payout is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if this payout is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if this payout is pending (requested or processing).
     */
    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_PROCESSING]);
    }

    /**
     * Check if this payout is finalized (completed, failed, or rejected).
     */
    public function isFinalized(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_REJECTED]);
    }

    /**
     * Get net amount (amount - fee).
     */
    public function getNetAmountAttribute(): float
    {
        return $this->amount - $this->fee;
    }

    /**
     * Get total amount (amount + fee).
     */
    public function getTotalAmountAttribute(): float
    {
        return $this->amount + $this->fee;
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2);
    }

    /**
     * Get formatted fee.
     */
    public function getFormattedFeeAttribute(): string
    {
        return number_format($this->fee, 2);
    }

    /**
     * Get formatted net amount.
     */
    public function getFormattedNetAmountAttribute(): string
    {
        return number_format($this->net_amount, 2);
    }

    /**
     * Get payout summary.
     */
    public function getSummaryAttribute(): string
    {
        return "Payout #{$this->id} - {$this->user->name} - {$this->formatted_amount} - {$this->status}";
    }

    /**
     * Get all statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_REQUESTED,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_REJECTED,
        ];
    }

    /**
     * Get payout statistics for a user.
     */
    public static function getUserPayoutStats(int $userId): array
    {
        $totalPayouts = static::forUser($userId)->count();
        $requestedPayouts = static::forUser($userId)->requested()->count();
        $processingPayouts = static::forUser($userId)->processing()->count();
        $completedPayouts = static::forUser($userId)->completed()->count();
        $failedPayouts = static::forUser($userId)->failed()->count();
        $rejectedPayouts = static::forUser($userId)->rejected()->count();

        $totalAmount = static::forUser($userId)->sum('amount');
        $totalFees = static::forUser($userId)->sum('fee');
        $totalNetAmount = $totalAmount - $totalFees;

        $completedAmount = static::forUser($userId)->completed()->sum('amount');
        $completedFees = static::forUser($userId)->completed()->sum('fee');
        $completedNetAmount = $completedAmount - $completedFees;

        return [
            'total_payouts' => $totalPayouts,
            'requested_payouts' => $requestedPayouts,
            'processing_payouts' => $processingPayouts,
            'completed_payouts' => $completedPayouts,
            'failed_payouts' => $failedPayouts,
            'rejected_payouts' => $rejectedPayouts,
            'total_amount' => $totalAmount,
            'total_fees' => $totalFees,
            'total_net_amount' => $totalNetAmount,
            'completed_amount' => $completedAmount,
            'completed_fees' => $completedFees,
            'completed_net_amount' => $completedNetAmount,
        ];
    }

    /**
     * Get system-wide payout statistics.
     */
    public static function getSystemPayoutStats(): array
    {
        $totalPayouts = static::count();
        $requestedPayouts = static::requested()->count();
        $processingPayouts = static::processing()->count();
        $completedPayouts = static::completed()->count();
        $failedPayouts = static::failed()->count();
        $rejectedPayouts = static::rejected()->count();

        $totalAmount = static::sum('amount');
        $totalFees = static::sum('fee');
        $totalNetAmount = $totalAmount - $totalFees;

        $completedAmount = static::completed()->sum('amount');
        $completedFees = static::completed()->sum('fee');
        $completedNetAmount = $completedAmount - $completedFees;

        return [
            'total_payouts' => $totalPayouts,
            'requested_payouts' => $requestedPayouts,
            'processing_payouts' => $processingPayouts,
            'completed_payouts' => $completedPayouts,
            'failed_payouts' => $failedPayouts,
            'rejected_payouts' => $rejectedPayouts,
            'total_amount' => $totalAmount,
            'total_fees' => $totalFees,
            'total_net_amount' => $totalNetAmount,
            'completed_amount' => $completedAmount,
            'completed_fees' => $completedFees,
            'completed_net_amount' => $completedNetAmount,
        ];
    }

    /**
     * Process pending payouts.
     */
    public static function processPendingPayouts(array $payoutIds, string $newStatus, ?int $processedBy = null): int
    {
        if (!in_array($newStatus, [self::STATUS_PROCESSING, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_REJECTED])) {
            return 0;
        }

        $updateData = ['status' => $newStatus];

        if ($processedBy) {
            $updateData['processed_by'] = $processedBy;
        }

        if (in_array($newStatus, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_REJECTED])) {
            $updateData['processed_at'] = now();
        }

        return static::whereIn('id', $payoutIds)
            ->whereIn('status', [self::STATUS_REQUESTED, self::STATUS_PROCESSING])
            ->update($updateData);
    }

    /**
     * Get payouts ready for processing.
     */
    public static function getPayoutsReadyForProcessing(int $limit = 50)
    {
        return static::requested()
            ->with(['user', 'wallet'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get payouts by processor.
     */
    public static function getPayoutsByProcessor(int $processorId)
    {
        return static::byProcessor($processorId)
            ->with(['user', 'wallet'])
            ->orderBy('processed_at', 'desc')
            ->get();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payout) {
            if (empty($payout->status)) {
                $payout->status = self::STATUS_REQUESTED;
            }
            if (empty($payout->fee)) {
                $payout->fee = 0;
            }
        });
    }
}
