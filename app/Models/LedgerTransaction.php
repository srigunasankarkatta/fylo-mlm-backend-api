<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class LedgerTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'user_from',
        'user_to',
        'wallet_from_id',
        'wallet_to_id',
        'type',
        'amount',
        'currency',
        'reference_id',
        'description',
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
     * Disable updated_at timestamp (immutable ledger).
     */
    public $timestamps = false;

    /**
     * Transaction types constants.
     */
    const TYPE_LEVEL_INCOME = 'level_income';
    const TYPE_FASTTRACK = 'fasttrack';
    const TYPE_CLUB_INCOME = 'club_income';
    const TYPE_AUTOPOOL_INCOME = 'autopool_income';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_REFUND = 'refund';
    const TYPE_PAYOUT = 'payout';
    const TYPE_FEE = 'fee';
    const TYPE_COMPANY_ALLOCATION = 'company_allocation';
    const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * Get the user who initiated the transaction.
     */
    public function userFrom()
    {
        return $this->belongsTo(User::class, 'user_from');
    }

    /**
     * Get the user who received the transaction.
     */
    public function userTo()
    {
        return $this->belongsTo(User::class, 'user_to');
    }

    /**
     * Get the source wallet.
     */
    public function walletFrom()
    {
        return $this->belongsTo(Wallet::class, 'wallet_from_id');
    }

    /**
     * Get the destination wallet.
     */
    public function walletTo()
    {
        return $this->belongsTo(Wallet::class, 'wallet_to_id');
    }

    /**
     * Get the reference object (e.g., UserPackage).
     */
    public function reference()
    {
        // This would need to be implemented based on reference_id logic
        // For now, we'll return null as it depends on the specific reference type
        return null;
    }

    /**
     * Scope a query to only include transactions for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_from', $userId)
                ->orWhere('user_to', $userId);
        });
    }

    /**
     * Scope a query to only include transactions by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include transactions by currency.
     */
    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope a query to only include transactions within date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include income transactions.
     */
    public function scopeIncome($query)
    {
        return $query->whereIn('type', [
            self::TYPE_LEVEL_INCOME,
            self::TYPE_FASTTRACK,
            self::TYPE_CLUB_INCOME,
            self::TYPE_AUTOPOOL_INCOME,
            self::TYPE_REFUND,
            self::TYPE_COMPANY_ALLOCATION,
        ]);
    }

    /**
     * Scope a query to only include expense transactions.
     */
    public function scopeExpense($query)
    {
        return $query->whereIn('type', [
            self::TYPE_PURCHASE,
            self::TYPE_PAYOUT,
            self::TYPE_FEE,
        ]);
    }

    /**
     * Scope a query to only include outgoing transactions for a user.
     */
    public function scopeOutgoing($query, int $userId)
    {
        return $query->where('user_from', $userId);
    }

    /**
     * Scope a query to only include incoming transactions for a user.
     */
    public function scopeIncoming($query, int $userId)
    {
        return $query->where('user_to', $userId);
    }

    /**
     * Create a new ledger transaction.
     */
    public static function createTransaction(array $data): LedgerTransaction
    {
        $data['uuid'] = $data['uuid'] ?? Str::uuid();

        return static::create($data);
    }

    /**
     * Create a wallet-to-wallet transfer transaction.
     */
    public static function createWalletTransfer(
        int $userFrom,
        int $userTo,
        int $walletFromId,
        int $walletToId,
        float $amount,
        string $currency = 'USD',
        ?string $description = null,
        ?int $referenceId = null
    ): LedgerTransaction {
        return static::createTransaction([
            'user_from' => $userFrom,
            'user_to' => $userTo,
            'wallet_from_id' => $walletFromId,
            'wallet_to_id' => $walletToId,
            'type' => self::TYPE_PAYOUT,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description ?? "Wallet transfer from user {$userFrom} to user {$userTo}",
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Create a commission income transaction.
     */
    public static function createCommissionIncome(
        int $userTo,
        int $walletToId,
        float $amount,
        string $currency = 'USD',
        ?string $description = null,
        ?int $referenceId = null
    ): LedgerTransaction {
        return static::createTransaction([
            'user_to' => $userTo,
            'wallet_to_id' => $walletToId,
            'type' => self::TYPE_LEVEL_INCOME,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description ?? "Commission income for user {$userTo}",
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Create a purchase transaction.
     */
    public static function createPurchase(
        int $userFrom,
        int $walletFromId,
        float $amount,
        string $currency = 'USD',
        ?string $description = null,
        ?int $referenceId = null
    ): LedgerTransaction {
        return static::createTransaction([
            'user_from' => $userFrom,
            'wallet_from_id' => $walletFromId,
            'type' => self::TYPE_PURCHASE,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description ?? "Purchase by user {$userFrom}",
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Create a company allocation transaction.
     */
    public static function createCompanyAllocation(
        int $userTo,
        int $walletToId,
        float $amount,
        string $currency = 'USD',
        ?string $description = null,
        ?int $referenceId = null
    ): LedgerTransaction {
        return static::createTransaction([
            'user_to' => $userTo,
            'wallet_to_id' => $walletToId,
            'type' => self::TYPE_COMPANY_ALLOCATION,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description ?? "Company allocation for user {$userTo}",
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    /**
     * Get transaction summary.
     */
    public function getSummaryAttribute(): string
    {
        $from = $this->userFrom ? "User #{$this->userFrom}" : 'System';
        $to = $this->userTo ? "User #{$this->userTo}" : 'System';
        return "{$this->type}: {$from} → {$to} ({$this->formatted_amount})";
    }

    /**
     * Check if this is an income transaction.
     */
    public function isIncome(): bool
    {
        return in_array($this->type, [
            self::TYPE_LEVEL_INCOME,
            self::TYPE_FASTTRACK,
            self::TYPE_CLUB_INCOME,
            self::TYPE_AUTOPOOL_INCOME,
            self::TYPE_REFUND,
            self::TYPE_COMPANY_ALLOCATION,
        ]);
    }

    /**
     * Check if this is an expense transaction.
     */
    public function isExpense(): bool
    {
        return in_array($this->type, [
            self::TYPE_PURCHASE,
            self::TYPE_PAYOUT,
            self::TYPE_FEE,
        ]);
    }

    /**
     * Get all transaction types.
     */
    public static function getTransactionTypes(): array
    {
        return [
            self::TYPE_LEVEL_INCOME,
            self::TYPE_FASTTRACK,
            self::TYPE_CLUB_INCOME,
            self::TYPE_AUTOPOOL_INCOME,
            self::TYPE_PURCHASE,
            self::TYPE_REFUND,
            self::TYPE_PAYOUT,
            self::TYPE_FEE,
            self::TYPE_COMPANY_ALLOCATION,
            self::TYPE_ADJUSTMENT,
        ];
    }

    /**
     * Get total income for a user.
     */
    public static function getTotalIncome(int $userId, string $currency = 'USD'): float
    {
        return static::incoming($userId)
            ->byCurrency($currency)
            ->income()
            ->sum('amount');
    }

    /**
     * Get total expenses for a user.
     */
    public static function getTotalExpenses(int $userId, string $currency = 'USD'): float
    {
        return static::outgoing($userId)
            ->byCurrency($currency)
            ->expense()
            ->sum('amount');
    }

    /**
     * Get net balance for a user.
     */
    public static function getNetBalance(int $userId, string $currency = 'USD'): float
    {
        $income = static::getTotalIncome($userId, $currency);
        $expenses = static::getTotalExpenses($userId, $currency);
        return $income - $expenses;
    }

    /**
     * Get transaction statistics.
     */
    public static function getTransactionStats(): array
    {
        $totalTransactions = static::count();
        $totalAmount = static::sum('amount');
        $totalIncome = static::income()->sum('amount');
        $totalExpenses = static::expense()->sum('amount');

        return [
            'total_transactions' => $totalTransactions,
            'total_amount' => $totalAmount,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_balance' => $totalIncome - $totalExpenses,
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = Str::uuid();
            }
        });
    }
}
