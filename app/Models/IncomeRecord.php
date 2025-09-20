<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IncomeRecord extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'origin_user_id',
        'user_package_id',
        'income_config_id',
        'income_type',
        'amount',
        'currency',
        'status',
        'ledger_transaction_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:8',
        'created_at' => 'datetime',
    ];

    /**
     * Disable updated_at timestamp (immutable records).
     */
    public $timestamps = false;

    /**
     * Income types constants.
     */
    const TYPE_LEVEL = 'level';
    const TYPE_FASTTRACK = 'fasttrack';
    const TYPE_CLUB = 'club';
    const TYPE_AUTOPOOL = 'autopool';
    const TYPE_OTHER = 'other';

    /**
     * Status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_REVERSED = 'reversed';

    /**
     * Get the user who receives this income.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who triggered this income (purchaser).
     */
    public function originUser()
    {
        return $this->belongsTo(User::class, 'origin_user_id');
    }

    /**
     * Get the user package that triggered this income.
     */
    public function userPackage()
    {
        return $this->belongsTo(UserPackage::class);
    }

    /**
     * Get the income config used for this record.
     */
    public function incomeConfig()
    {
        return $this->belongsTo(IncomeConfig::class);
    }

    /**
     * Get the ledger transaction associated with this income.
     */
    public function ledgerTransaction()
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    /**
     * Scope a query to only include records for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include records by origin user.
     */
    public function scopeByOriginUser($query, int $originUserId)
    {
        return $query->where('origin_user_id', $originUserId);
    }

    /**
     * Scope a query to only include records by income type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('income_type', $type);
    }

    /**
     * Scope a query to only include records by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include records by currency.
     */
    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope a query to only include pending records.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include paid records.
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope a query to only include reversed records.
     */
    public function scopeReversed($query)
    {
        return $query->where('status', self::STATUS_REVERSED);
    }

    /**
     * Scope a query to only include records within date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include records for a specific user package.
     */
    public function scopeForUserPackage($query, int $userPackageId)
    {
        return $query->where('user_package_id', $userPackageId);
    }

    /**
     * Create a new income record.
     */
    public static function createRecord(array $data): IncomeRecord
    {
        return static::create($data);
    }

    /**
     * Create a level income record.
     */
    public static function createLevelIncome(
        int $userId,
        int $originUserId,
        int $userPackageId,
        int $incomeConfigId,
        float $amount,
        string $currency = 'USD'
    ): IncomeRecord {
        return static::createRecord([
            'user_id' => $userId,
            'origin_user_id' => $originUserId,
            'user_package_id' => $userPackageId,
            'income_config_id' => $incomeConfigId,
            'income_type' => self::TYPE_LEVEL,
            'amount' => $amount,
            'currency' => $currency,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Create a fasttrack income record.
     */
    public static function createFasttrackIncome(
        int $userId,
        int $originUserId,
        int $userPackageId,
        int $incomeConfigId,
        float $amount,
        string $currency = 'USD'
    ): IncomeRecord {
        return static::createRecord([
            'user_id' => $userId,
            'origin_user_id' => $originUserId,
            'user_package_id' => $userPackageId,
            'income_config_id' => $incomeConfigId,
            'income_type' => self::TYPE_FASTTRACK,
            'amount' => $amount,
            'currency' => $currency,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Create a club income record.
     */
    public static function createClubIncome(
        int $userId,
        int $originUserId,
        int $userPackageId,
        int $incomeConfigId,
        float $amount,
        string $currency = 'USD'
    ): IncomeRecord {
        return static::createRecord([
            'user_id' => $userId,
            'origin_user_id' => $originUserId,
            'user_package_id' => $userPackageId,
            'income_config_id' => $incomeConfigId,
            'income_type' => self::TYPE_CLUB,
            'amount' => $amount,
            'currency' => $currency,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Create an autopool income record.
     */
    public static function createAutopoolIncome(
        int $userId,
        int $originUserId,
        int $userPackageId,
        int $incomeConfigId,
        float $amount,
        string $currency = 'USD'
    ): IncomeRecord {
        return static::createRecord([
            'user_id' => $userId,
            'origin_user_id' => $originUserId,
            'user_package_id' => $userPackageId,
            'income_config_id' => $incomeConfigId,
            'income_type' => self::TYPE_AUTOPOOL,
            'amount' => $amount,
            'currency' => $currency,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Mark this record as paid.
     */
    public function markAsPaid(int $ledgerTransactionId = null): bool
    {
        return $this->update([
            'status' => self::STATUS_PAID,
            'ledger_transaction_id' => $ledgerTransactionId,
        ]);
    }

    /**
     * Mark this record as reversed.
     */
    public function markAsReversed(): bool
    {
        return $this->update([
            'status' => self::STATUS_REVERSED,
        ]);
    }

    /**
     * Check if this record is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if this record is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if this record is reversed.
     */
    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    /**
     * Get income record summary.
     */
    public function getSummaryAttribute(): string
    {
        $origin = $this->originUser ? "from User #{$this->originUser->id}" : 'from System';
        return "{$this->income_type} income {$origin} - {$this->formatted_amount}";
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
     * Get all statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PAID,
            self::STATUS_REVERSED,
        ];
    }

    /**
     * Get total income for a user.
     */
    public static function getTotalIncome(int $userId, string $currency = 'USD'): float
    {
        return static::forUser($userId)
            ->byCurrency($currency)
            ->paid()
            ->sum('amount');
    }

    /**
     * Get total pending income for a user.
     */
    public static function getTotalPendingIncome(int $userId, string $currency = 'USD'): float
    {
        return static::forUser($userId)
            ->byCurrency($currency)
            ->pending()
            ->sum('amount');
    }

    /**
     * Get total income by type for a user.
     */
    public static function getTotalIncomeByType(int $userId, string $type, string $currency = 'USD'): float
    {
        return static::forUser($userId)
            ->byType($type)
            ->byCurrency($currency)
            ->paid()
            ->sum('amount');
    }

    /**
     * Get income statistics for a user.
     */
    public static function getUserIncomeStats(int $userId, string $currency = 'USD'): array
    {
        $totalPaid = static::getTotalIncome($userId, $currency);
        $totalPending = static::getTotalPendingIncome($userId, $currency);
        $totalReversed = static::forUser($userId)
            ->byCurrency($currency)
            ->reversed()
            ->sum('amount');

        $byType = [];
        foreach (static::getIncomeTypes() as $type) {
            $byType[$type] = static::getTotalIncomeByType($userId, $type, $currency);
        }

        return [
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
            'total_reversed' => $totalReversed,
            'total_earned' => $totalPaid + $totalPending,
            'by_type' => $byType,
        ];
    }

    /**
     * Get income statistics for the system.
     */
    public static function getSystemIncomeStats(): array
    {
        $totalRecords = static::count();
        $totalAmount = static::sum('amount');
        $totalPaid = static::paid()->sum('amount');
        $totalPending = static::pending()->sum('amount');
        $totalReversed = static::reversed()->sum('amount');

        return [
            'total_records' => $totalRecords,
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
            'total_reversed' => $totalReversed,
        ];
    }

    /**
     * Process pending income records (mark as paid).
     */
    public static function processPendingRecords(array $recordIds, int $ledgerTransactionId = null): int
    {
        return static::whereIn('id', $recordIds)
            ->pending()
            ->update([
                'status' => self::STATUS_PAID,
                'ledger_transaction_id' => $ledgerTransactionId,
            ]);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($record) {
            if (empty($record->status)) {
                $record->status = self::STATUS_PENDING;
            }
        });
    }
}
