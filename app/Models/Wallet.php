<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'wallet_type',
        'currency',
        'balance',
        'pending_balance',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'balance' => 'decimal:8',
        'pending_balance' => 'decimal:8',
    ];

    /**
     * Wallet types constants.
     */
    const TYPE_COMMISSION = 'commission';
    const TYPE_FASTTRACK = 'fasttrack';
    const TYPE_AUTOPOOL = 'autopool';
    const TYPE_CLUB = 'club';
    const TYPE_MAIN = 'main';
    const TYPE_COMPANY_TOTAL = 'company_total';

    /**
     * Get the user who owns this wallet.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include user wallets.
     */
    public function scopeUserWallets($query)
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope a query to only include company wallets.
     */
    public function scopeCompanyWallets($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope a query to only include wallets by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('wallet_type', $type);
    }

    /**
     * Scope a query to only include wallets by currency.
     */
    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope a query to only include wallets for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get or create a wallet for a user.
     */
    public static function getOrCreate(int $userId, string $type, string $currency = 'USD'): Wallet
    {
        return static::firstOrCreate(
            [
                'user_id' => $userId,
                'wallet_type' => $type,
                'currency' => $currency,
            ],
            [
                'balance' => 0,
                'pending_balance' => 0,
            ]
        );
    }

    /**
     * Get or create a company wallet.
     */
    public static function getOrCreateCompany(string $type, string $currency = 'USD'): Wallet
    {
        return static::firstOrCreate(
            [
                'user_id' => null,
                'wallet_type' => $type,
                'currency' => $currency,
            ],
            [
                'balance' => 0,
                'pending_balance' => 0,
            ]
        );
    }

    /**
     * Check if this is a company wallet.
     */
    public function isCompanyWallet(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Check if this is a user wallet.
     */
    public function isUserWallet(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Get total available balance (balance + pending).
     */
    public function getTotalBalanceAttribute(): float
    {
        return $this->balance + $this->pending_balance;
    }

    /**
     * Get formatted balance.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 2) . ' ' . $this->currency;
    }

    /**
     * Get formatted pending balance.
     */
    public function getFormattedPendingBalanceAttribute(): string
    {
        return number_format($this->pending_balance, 2) . ' ' . $this->currency;
    }

    /**
     * Get formatted total balance.
     */
    public function getFormattedTotalBalanceAttribute(): string
    {
        return number_format($this->total_balance, 2) . ' ' . $this->currency;
    }

    /**
     * Add balance to the wallet.
     */
    public function addBalance(float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        return $this->increment('balance', $amount);
    }

    /**
     * Subtract balance from the wallet.
     */
    public function subtractBalance(float $amount): bool
    {
        if ($amount <= 0 || $this->balance < $amount) {
            return false;
        }

        return $this->decrement('balance', $amount);
    }

    /**
     * Add pending balance to the wallet.
     */
    public function addPendingBalance(float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        return $this->increment('pending_balance', $amount);
    }

    /**
     * Subtract pending balance from the wallet.
     */
    public function subtractPendingBalance(float $amount): bool
    {
        if ($amount <= 0 || $this->pending_balance < $amount) {
            return false;
        }

        return $this->decrement('pending_balance', $amount);
    }

    /**
     * Move pending balance to main balance.
     */
    public function confirmPendingBalance(): bool
    {
        if ($this->pending_balance <= 0) {
            return false;
        }

        return DB::transaction(function () {
            $this->increment('balance', $this->pending_balance);
            $this->update(['pending_balance' => 0]);
            return true;
        });
    }

    /**
     * Transfer balance to another wallet.
     */
    public function transferTo(Wallet $targetWallet, float $amount): bool
    {
        if ($amount <= 0 || $this->balance < $amount || $this->currency !== $targetWallet->currency) {
            return false;
        }

        return DB::transaction(function () use ($targetWallet, $amount) {
            $this->subtractBalance($amount);
            $targetWallet->addBalance($amount);
            return true;
        });
    }

    /**
     * Get wallet summary.
     */
    public function getSummaryAttribute(): string
    {
        $owner = $this->isCompanyWallet() ? 'Company' : "User #{$this->user_id}";
        return "{$owner} - {$this->wallet_type} - {$this->formatted_balance}";
    }

    /**
     * Get all wallet types.
     */
    public static function getWalletTypes(): array
    {
        return [
            self::TYPE_COMMISSION,
            self::TYPE_FASTTRACK,
            self::TYPE_AUTOPOOL,
            self::TYPE_CLUB,
            self::TYPE_MAIN,
            self::TYPE_COMPANY_TOTAL,
        ];
    }

    /**
     * Get total balance for a user across all wallets.
     */
    public static function getTotalUserBalance(int $userId, string $currency = 'USD'): float
    {
        return static::where('user_id', $userId)
            ->where('currency', $currency)
            ->sum('balance');
    }

    /**
     * Get total pending balance for a user across all wallets.
     */
    public static function getTotalUserPendingBalance(int $userId, string $currency = 'USD'): float
    {
        return static::where('user_id', $userId)
            ->where('currency', $currency)
            ->sum('pending_balance');
    }

    /**
     * Get total company balance for a specific wallet type.
     */
    public static function getTotalCompanyBalance(string $type, string $currency = 'USD'): float
    {
        return static::whereNull('user_id')
            ->where('wallet_type', $type)
            ->where('currency', $currency)
            ->sum('balance');
    }

    /**
     * Get wallet statistics.
     */
    public static function getWalletStats(): array
    {
        $totalUserWallets = static::whereNotNull('user_id')->count();
        $totalCompanyWallets = static::whereNull('user_id')->count();
        $totalBalance = static::sum('balance');
        $totalPendingBalance = static::sum('pending_balance');

        return [
            'total_user_wallets' => $totalUserWallets,
            'total_company_wallets' => $totalCompanyWallets,
            'total_balance' => $totalBalance,
            'total_pending_balance' => $totalPendingBalance,
            'total_available' => $totalBalance + $totalPendingBalance,
        ];
    }
}
