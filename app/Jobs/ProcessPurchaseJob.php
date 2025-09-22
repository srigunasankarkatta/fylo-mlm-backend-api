<?php

namespace App\Jobs;

use App\Models\UserPackage;
use App\Models\User;
use App\Models\UserTree;
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

class ProcessPurchaseJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $userPackageId;

    // Precision for bc math
    protected int $scale = 8;

    public function __construct(int $userPackageId)
    {
        $this->userPackageId = $userPackageId;
    }

    public function handle()
    {
        // Load order and related models
        $order = UserPackage::with(['user', 'package'])->find($this->userPackageId);
        if (! $order) {
            Log::warning("ProcessPurchaseJob: order not found id {$this->userPackageId}");
            return;
        }

        // Idempotency: if already processed, skip
        if ($order->processed_at) {
            Log::info("ProcessPurchaseJob: already processed order id {$order->id}");
            return;
        }

        // If another worker is processing, honor the flag
        if ($order->processing) {
            // Optionally re-dispatch later
            Log::info("ProcessPurchaseJob: order already locked for processing id {$order->id}");
            return;
        }

        DB::transaction(function () use ($order) {
            // Mark processing
            $order->update(['processing' => true]);

            try {
                // 1) Fasttrack: compute configured percentage, credit immediate upline
                $this->distributeFasttrack($order);

                // 2) Level income: fixed amount to every upline up the tree
                $this->distributeLevelIncome($order);

                // 3) Company allocation for AutoPool (deduct configured portion and credit company_total wallet)
                $this->allocateCompanyPortionForAutoPool($order);

                // 4) Club income: place user in sponsor's club tree and distribute income
                $this->processClubIncome($order);

                // NOTE: AutoPool distribution is handled by background jobs:
                // dispatch(new ProcessAutoPoolJob(...));
                // We'll leave that for separate jobs.

                // mark processed
                $order->update([
                    'processing' => false,
                    'processed_at' => now()
                ]);
            } catch (\Throwable $e) {
                // revert processing flag so it can be retried
                $order->update(['processing' => false]);
                Log::error("ProcessPurchaseJob failed for order {$order->id}: {$e->getMessage()}");
                throw $e; // allow job to be retried according to queue settings
            }
        });
    }

    /**
     * FASTTRACK distribution:
     * - Get active fasttrack config(s) for the package (or global fallback)
     * - For each relevant config, compute amount = order.amount_paid * percentage
     * - Credit immediate parent (placement parent). If no parent, credit company_total wallet.
     */
    protected function distributeFasttrack(UserPackage $order)
    {
        // Avoid double-processing: check if any fasttrack income_records exist for this order
        $exists = IncomeRecord::where('user_package_id', $order->id)
            ->where('income_type', 'fasttrack')
            ->exists();
        if ($exists) {
            Log::info("Fasttrack already processed for order {$order->id}");
            return;
        }

        // Fetch package-specific configs first, then global
        $configs = IncomeConfig::where('income_type', 'fasttrack')
            ->where(function ($q) use ($order) {
                $q->where('package_id', $order->package_id)
                    ->orWhereNull('package_id');
            })
            ->where('is_active', true)
            ->orderByDesc('package_id') // prefer package specific
            ->get();

        if ($configs->isEmpty()) {
            Log::info("No fasttrack config found for package {$order->package_id}, skipping fasttrack.");
            return;
        }

        // Identify immediate placement parent (user_tree parent_id)
        $placement = UserTree::where('user_id', $order->user_id)->first();
        $parentId = $placement ? $placement->parent_id : null;

        foreach ($configs as $cfg) {
            // Normalize percentage to fraction: e.g., cfg.percentage = 10 => 0.10 ; 0.1 => 0.001
            $pct = $this->normalizePercentage((string) $cfg->percentage);

            // Compute amount = amount_paid * pct
            $amount = $this->bcMul((string)$order->amount_paid, $pct, $this->scale);

            if ($this->bcCmp($amount, '0', $this->scale) <= 0) {
                // Nothing to distribute for this config
                continue;
            }

            // Determine beneficiary: immediate parent if exists, else company_total
            if ($parentId) {
                $beneficiaryUserId = $parentId;
                $beneficiaryWallet = $this->getOrCreateWallet($beneficiaryUserId, 'fasttrack', $order->currency ?? 'USD');
            } else {
                // company wallet: user_id = null, wallet_type = company_total
                $beneficiaryUserId = null;
                $beneficiaryWallet = $this->getOrCreateCompanyWallet('company_total', $order->currency ?? 'USD');
            }

            // ledger-first: create ledger transaction
            $ledger = LedgerTransaction::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'user_from' => $order->user_id,
                'user_to' => $beneficiaryUserId,
                'wallet_from_id' => null, // from payment (we can leave null or point to company/main wallet)
                'wallet_to_id' => $beneficiaryWallet->id,
                'type' => 'fasttrack',
                'amount' => $amount,
                'currency' => $order->currency ?? 'USD',
                'reference_id' => $order->id,
                'description' => "Fasttrack payout for purchase {$order->id} (cfg {$cfg->id})"
            ]);

            // update wallet balance (row lock)
            Wallet::where('id', $beneficiaryWallet->id)->lockForUpdate()
                ->increment('balance', $amount);

            // create income_record
            IncomeRecord::create([
                'user_id' => $beneficiaryUserId,
                'origin_user_id' => $order->user_id,
                'user_package_id' => $order->id,
                'income_config_id' => $cfg->id,
                'income_type' => 'fasttrack',
                'amount' => $amount,
                'currency' => $order->currency ?? 'USD',
                'status' => 'paid',
                'ledger_transaction_id' => $ledger->id,
            ]);
        }
    }

    /**
     * LEVEL INCOME distribution:
     * - Each ancestor in placement chain gets a fixed amount (recommended: income_configs.metadata.fixed_amount)
     * - Walk up parent_id until null (root).
     */
    protected function distributeLevelIncome(UserPackage $order)
    {
        // Avoid double-processing
        $exists = IncomeRecord::where('user_package_id', $order->id)
            ->where('income_type', 'level')
            ->exists();
        if ($exists) {
            Log::info("Level income already processed for order {$order->id}");
            return;
        }

        // Obtain level config (prefer package-specific? we take global/any)
        $cfg = IncomeConfig::where('income_type', 'level')
            ->where('is_active', true)
            ->orderByDesc('package_id')
            ->first();

        // Determine fixed amount:
        // Prefer metadata.fixed_amount (explicit unit). Else if percentage present, treat percentage as fixed units (legacy).
        $levelAmount = null;
        if ($cfg) {
            $meta = $cfg->metadata ?? [];
            if (isset($meta['fixed_amount'])) {
                $levelAmount = (string) $meta['fixed_amount'];
            } elseif (!is_null($cfg->percentage)) {
                // Treat percentage as fixed amount for level (not %)
                $levelAmount = (string) $cfg->percentage;
            }
        }
        // Default fallback
        if (is_null($levelAmount) || $levelAmount === '') {
            $levelAmount = '0.5';
        }

        // Walk up the placement tree: get parent chain
        $placement = UserTree::where('user_id', $order->user_id)->first();
        $parentId = $placement ? $placement->parent_id : null;

        $ancestorsCount = 0;
        while ($parentId) {
            $ancestor = User::find($parentId);
            if (! $ancestor) break;

            $amount = $this->normalizeMoneyString($levelAmount);

            if ($this->bcCmp($amount, '0', $this->scale) > 0) {
                // credit ancestor's commission wallet
                $wallet = $this->getOrCreateWallet($ancestor->id, 'commission', $order->currency ?? 'USD');

                $ledger = LedgerTransaction::create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'user_from' => $order->user_id,
                    'user_to' => $ancestor->id,
                    'wallet_from_id' => null,
                    'wallet_to_id' => $wallet->id,
                    'type' => 'level_income',
                    'amount' => $amount,
                    'currency' => $order->currency ?? 'USD',
                    'reference_id' => $order->id,
                    'description' => "Level income for purchase {$order->id} to upline {$ancestor->id}"
                ]);

                Wallet::where('id', $wallet->id)->lockForUpdate()
                    ->increment('balance', $amount);

                IncomeRecord::create([
                    'user_id' => $ancestor->id,
                    'origin_user_id' => $order->user_id,
                    'user_package_id' => $order->id,
                    'income_config_id' => $cfg ? $cfg->id : null,
                    'income_type' => 'level',
                    'amount' => $amount,
                    'currency' => $order->currency ?? 'USD',
                    'status' => 'paid',
                    'ledger_transaction_id' => $ledger->id,
                ]);
            }

            // move up one level
            $parentPlacement = UserTree::where('user_id', $ancestor->id)->first();
            $parentId = $parentPlacement ? $parentPlacement->parent_id : null;

            $ancestorsCount++;
            // safety: avoid infinite loops
            if ($ancestorsCount > 1000) {
                Log::error("Placement chain exceeded 1000 for order {$order->id}; aborting level distribution.");
                break;
            }
        }
    }

    /**
     * Company allocation for AutoPool (a configured percentage goes to company_total wallet)
     * Implementation:
     * - find income_config(s) with income_type 'fasttrack' and special metadata key 'company_allocation' or name 'Company Deduction'
     * - fallback: find income_config where income_type='fasttrack' and package_id=null and metadata.company_share present
     * - For simplicity: we look for config with metadata.company_share (fraction or percent) or named 'Company Deduction'
     */
    protected function allocateCompanyPortionForAutoPool(UserPackage $order)
    {
        // Avoid duplicate
        $exists = IncomeRecord::where('user_package_id', $order->id)
            ->where('income_type', 'company_allocation')
            ->exists();
        if ($exists) {
            Log::info("Company allocation already processed for order {$order->id}");
            return;
        }

        // Try to find explicit config
        $cfg = IncomeConfig::where('income_type', 'fasttrack')
            ->where(function ($q) {
                $q->where('name', 'like', '%Company%')
                    ->orWhereJsonContains('metadata->company_share', true);
            })->where('is_active', true)->first();

        // Alternatively, check metadata.company_share on any fasttrack config for package
        if (! $cfg) {
            $cfg = IncomeConfig::where('income_type', 'fasttrack')
                ->where(function ($q) use ($order) {
                    $q->where('package_id', $order->package_id)
                        ->orWhereNull('package_id');
                })
                ->where('is_active', true)
                ->first();
        }

        if (! $cfg) {
            // No company allocation configured
            Log::info("No company allocation config found for order {$order->id}");
            return;
        }

        $meta = $cfg->metadata ?? [];
        $companyShare = null;
        if (isset($meta['company_share'])) {
            // admin stored as fraction or percent
            $companyShare = (string) $meta['company_share'];
        } elseif (!is_null($cfg->percentage)) {
            // fallback: maybe admin used percentage field for company too — skip here
            // We won't double use fasttrack percentage as company share by default
            $companyShare = null;
        }

        if (is_null($companyShare)) {
            // config not explicit; nothing to allocate
            return;
        }

        $pct = $this->normalizePercentage($companyShare);
        $amount = $this->bcMul((string)$order->amount_paid, $pct, $this->scale);
        if ($this->bcCmp($amount, '0', $this->scale) <= 0) {
            return;
        }

        $companyWallet = $this->getOrCreateCompanyWallet('company_total', $order->currency ?? 'USD');

        $ledger = LedgerTransaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_from' => $order->user_id,
            'user_to' => null,
            'wallet_from_id' => null,
            'wallet_to_id' => $companyWallet->id,
            'type' => 'company_allocation',
            'amount' => $amount,
            'currency' => $order->currency ?? 'USD',
            'reference_id' => $order->id,
            'description' => "Company allocation for AutoPool from order {$order->id}"
        ]);

        Wallet::where('id', $companyWallet->id)->lockForUpdate()->increment('balance', $amount);

        IncomeRecord::create([
            'user_id' => null,
            'origin_user_id' => $order->user_id,
            'user_package_id' => $order->id,
            'income_config_id' => $cfg->id,
            'income_type' => 'company_allocation',
            'amount' => $amount,
            'currency' => $order->currency ?? 'USD',
            'status' => 'paid',
            'ledger_transaction_id' => $ledger->id,
        ]);
    }

    // ----------------- Helper methods -----------------

    /**
     * Normalize percentage input into a decimal fraction suitable for multiplication with amount.
     * Accepts: "10" => "0.10"  ; "0.1" => "0.001"  (we attempt to guess)
     *
     * Heuristic:
     *  - If value >= 1 and <= 100 => treat as percent (10 => 0.10)
     *  - If value > 100 => treat as percent too (500 => 5.00)
     *  - If value < 1 => treat as fraction of 1 meaning either 0.1 (10%) or 0.01 (1%)
     *    We assume admin will use either 10 (for 10%) or 0.1 (for 0.1%). This heuristic may vary.
     */
    protected function normalizePercentage(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '0';

        // ensure numeric
        if (!is_numeric($raw)) return '0';

        // use bccomp to compare
        if ($this->bcCmp($raw, '1', $this->scale) >= 0) {
            // raw >= 1 => treat as percent -> divide by 100
            return $this->bcDiv($raw, '100', $this->scale + 4);
        }

        // raw < 1 => we assume it's a fractional percent? treat as fraction of 1 (i.e., 0.10 => 0.10)
        return $raw;
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

        if (! $wallet) {
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

    /**
     * Company wallet helper (user_id = null)
     */
    protected function getOrCreateCompanyWallet(string $walletType, string $currency = 'USD'): Wallet
    {
        return $this->getOrCreateWallet(null, $walletType, $currency);
    }

    // ----------------- bc math helpers -----------------

    protected function bcMul(string $a, string $b, int $scale = null): string
    {
        $s = $scale ?? $this->scale;
        return bcmul($a, $b, $s);
    }

    protected function bcAdd(string $a, string $b, int $scale = null): string
    {
        $s = $scale ?? $this->scale;
        return bcadd($a, $b, $s);
    }

    protected function bcDiv(string $a, string $b, int $scale = null): string
    {
        $s = $scale ?? $this->scale;
        if ($b === '0' || $b === 0) return '0';
        return bcdiv($a, $b, $s);
    }

    protected function bcCmp(string $a, string $b, int $scale = null): int
    {
        $s = $scale ?? $this->scale;
        return bccomp($a, $b, $s);
    }

    protected function normalizeMoneyString(string $v): string
    {
        // Ensure value as numeric string with scale
        if (!is_numeric($v)) return '0';
        return number_format((float)$v, $this->scale, '.', '');
    }

    /**
     * Process club income by dispatching ProcessClubJob
     */
    protected function processClubIncome(UserPackage $order)
    {
        // Find the sponsor (immediate upline) for club placement
        $placement = UserTree::where('user_id', $order->user_id)->first();
        $sponsorId = $placement ? $placement->parent_id : null;

        if (!$sponsorId) {
            Log::info("ProcessPurchaseJob: No sponsor found for club placement for user {$order->user_id}");
            return;
        }

        // Dispatch club job
        dispatch(new \App\Jobs\ProcessClubJob($order->user_id, $sponsorId, $order->package_id));
        Log::info("ProcessPurchaseJob: Dispatched ProcessClubJob for user {$order->user_id} under sponsor {$sponsorId}");
    }
}
