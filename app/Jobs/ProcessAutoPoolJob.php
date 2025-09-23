<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\AutoPoolEntry;
use App\Models\IncomeConfig;
use App\Models\Wallet;
use App\Models\LedgerTransaction;
use App\Models\IncomeRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAutoPoolJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected int $packageId;
    protected float $amount;

    // Precision for bc math
    protected int $scale = 8;

    public function __construct(int $packageId, float $amount)
    {
        $this->packageId = $packageId;
        $this->amount = $amount;
    }

    public function handle()
    {
        Log::info("ProcessAutoPoolJob: Processing autopool distribution for package {$this->packageId} with amount {$this->amount}");

        DB::transaction(function () {
            // Get all active autopool entries
            $entries = AutoPoolEntry::where('status', 'active')
                ->orderBy('pool_level')
                ->orderBy('pool_sub_level')
                ->get();

            if ($entries->isEmpty()) {
                Log::info("ProcessAutoPoolJob: No active autopool entries found");
                return;
            }

            // Distribute to each entry based on their level and sub_level
            foreach ($entries as $entry) {
                $this->distributeToEntry($entry);
            }
        });
    }

    /**
     * Distribute autopool income to a specific entry
     */
    protected function distributeToEntry(AutoPoolEntry $entry)
    {
        // Get income config for this level and sub_level
        $config = IncomeConfig::where('income_type', 'autopool')
            ->where('level', $entry->pool_level)
            ->where('sub_level', $entry->pool_sub_level)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            Log::info("ProcessAutoPoolJob: No config found for level {$entry->pool_level}, sub_level {$entry->pool_sub_level}");
            return;
        }

        // Calculate amount based on percentage
        $percentage = $config->percentage / 100; // Convert percentage to decimal
        $amount = $this->bcMul((string)$this->amount, (string)$percentage, $this->scale);

        if ($this->bcCmp($amount, '0', $this->scale) <= 0) {
            Log::info("ProcessAutoPoolJob: No amount to distribute for entry {$entry->id}");
            return;
        }

        // Get or create user's autopool wallet
        $wallet = $this->getOrCreateWallet($entry->user_id, 'autopool', 'USD');

        // Create ledger transaction
        $ledger = LedgerTransaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_from' => null, // From company
            'user_to' => $entry->user_id,
            'wallet_from_id' => null,
            'wallet_to_id' => $wallet->id,
            'type' => 'autopool_income',
            'amount' => $amount,
            'currency' => 'USD',
            'reference_id' => $entry->id,
            'description' => "AutoPool L{$entry->pool_level}S{$entry->pool_sub_level} for user {$entry->user_id}"
        ]);

        // Update wallet balance
        Wallet::where('id', $wallet->id)->lockForUpdate()->increment('balance', $amount);

        // Create income record
        IncomeRecord::create([
            'user_id' => $entry->user_id,
            'origin_user_id' => null,
            'user_package_id' => $this->packageId > 0 ? $this->packageId : null,
            'income_config_id' => $config->id,
            'income_type' => 'autopool',
            'amount' => $amount,
            'currency' => 'USD',
            'status' => 'paid',
            'ledger_transaction_id' => $ledger->id,
            'reference_id' => $entry->id,
        ]);

        Log::info("ProcessAutoPoolJob: Credited {$amount} to user {$entry->user_id}'s autopool wallet (L{$entry->pool_level}S{$entry->pool_sub_level})");
    }

    /**
     * Get or create a user's wallet for a given type and currency
     */
    protected function getOrCreateWallet(?int $userId, string $walletType, string $currency = 'USD'): Wallet
    {
        $wallet = Wallet::where('user_id', $userId)
            ->where('wallet_type', $walletType)
            ->where('currency', $currency)
            ->first();

        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id' => $userId,
                'wallet_type' => $walletType,
                'currency' => $currency,
                'balance' => 0,
                'pending_balance' => 0
            ]);
        }

        return $wallet;
    }

    // ----------------- bc math helpers -----------------

    protected function bcMul(string $a, string $b, int $scale = null): string
    {
        $s = $scale ?? $this->scale;
        return bcmul($a, $b, $s);
    }

    protected function bcCmp(string $a, string $b, int $scale = null): int
    {
        $s = $scale ?? $this->scale;
        return bccomp($a, $b, $s);
    }
}
