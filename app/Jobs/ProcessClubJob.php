<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\ClubEntry;
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

class ProcessClubJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected int $userId;
    protected int $sponsorId;
    protected int $packageId;

    // Precision for bc math
    protected int $scale = 8;

    public function __construct(int $userId, int $sponsorId, int $packageId)
    {
        $this->userId = $userId;
        $this->sponsorId = $sponsorId;
        $this->packageId = $packageId;
    }

    public function handle()
    {
        Log::info("ProcessClubJob: Processing club entry for user {$this->userId} under sponsor {$this->sponsorId}");

        DB::transaction(function () {
            // 1) Place new user in sponsor's Club tree
            $entry = $this->placeInClub($this->userId, $this->sponsorId);

            // 2) Check if any levels are now completed and credit income accordingly
            $this->checkAndDistributeCompletedLevels($entry);
        });
    }

    /**
     * Place a user in the sponsor's club tree using BFS
     */
    protected function placeInClub(int $userId, int $sponsorId): ClubEntry
    {
        // Check if already placed (idempotency)
        $existing = ClubEntry::where('user_id', $userId)
            ->where('sponsor_id', $sponsorId)
            ->first();

        if ($existing) {
            Log::info("ProcessClubJob: User {$userId} already placed in sponsor {$sponsorId}'s club tree");
            return $existing;
        }

        // Find next available slot using BFS
        $queue = [$sponsorId];
        $visited = [];
        $level = 1;

        while (!empty($queue)) {
            $currentSponsorId = array_shift($queue);

            if (in_array($currentSponsorId, $visited)) {
                continue;
            }
            $visited[] = $currentSponsorId;

            // Check if this sponsor has available slots at current level
            $childrenCount = ClubEntry::where('sponsor_id', $currentSponsorId)
                ->where('level', $level)
                ->count();

            $maxSlotsAtLevel = pow(4, $level); // 4^level slots at each level

            if ($childrenCount < $maxSlotsAtLevel) {
                // Found available slot
                $entry = ClubEntry::create([
                    'user_id' => $userId,
                    'sponsor_id' => $currentSponsorId,
                    'level' => $level,
                    'status' => 'active'
                ]);

                Log::info("ProcessClubJob: Placed user {$userId} in sponsor {$currentSponsorId}'s club at level {$level}");
                return $entry;
            }

            // Move to next level and add children to queue
            $level++;
            if ($level > 10) {
                Log::error("ProcessClubJob: Maximum club level (10) reached, no slots available");
                break;
            }

            // Add children from current level to queue for next level
            $children = ClubEntry::where('sponsor_id', $currentSponsorId)
                ->where('level', $level - 1)
                ->pluck('user_id')
                ->toArray();

            $queue = array_merge($queue, $children);
        }

        throw new \Exception("No slot found in club tree for user {$userId} under sponsor {$sponsorId}");
    }

    /**
     * Check if any club levels are now completed and distribute income accordingly
     * Club income is only credited when a level threshold is reached:
     * Level 1: 4 members, Level 2: 16 members, Level 3: 64 members, etc.
     */
    protected function checkAndDistributeCompletedLevels(ClubEntry $entry)
    {
        $sponsorId = $entry->sponsor_id;

        // Check each level from 1 to 10 to see if it's now completed
        for ($level = 1; $level <= 10; $level++) {
            $this->checkLevelCompletion($sponsorId, $level);
        }
    }

    /**
     * Check if a specific level is completed and credit income if so
     */
    protected function checkLevelCompletion(int $sponsorId, int $level)
    {
        // Calculate required members for this level: 4^level
        $requiredMembers = pow(4, $level);

        // Count current members at this level
        $currentMembers = ClubEntry::where('sponsor_id', $sponsorId)
            ->where('level', $level)
            ->count();

        Log::info("ProcessClubJob: Level {$level} for sponsor {$sponsorId}: {$currentMembers}/{$requiredMembers} members");

        // Check if level is now completed
        if ($currentMembers >= $requiredMembers) {
            // Check if income was already credited for this level
            $alreadyCredited = IncomeRecord::where('user_id', $sponsorId)
                ->where('income_type', 'club')
                ->where('reference_id', $sponsorId . '_level_' . $level)
                ->exists();

            if (!$alreadyCredited) {
                $this->distributeClubIncomeForLevel($sponsorId, $level);
            } else {
                Log::info("ProcessClubJob: Level {$level} income already credited to sponsor {$sponsorId}");
            }
        }
    }

    /**
     * Distribute club income for a completed level
     */
    protected function distributeClubIncomeForLevel(int $sponsorId, int $level)
    {
        // Fetch config for this level
        $config = IncomeConfig::where('income_type', 'club')
            ->where('level', $level)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            Log::info("ProcessClubJob: No club income config found for level {$level}");
            return;
        }

        // Determine amount from config
        $amount = $this->getClubIncomeAmount($config, $level);
        if (!$amount || $this->bcCmp($amount, '0', $this->scale) <= 0) {
            Log::info("ProcessClubJob: No amount configured for club level {$level}");
            return;
        }

        // Get or create sponsor's club wallet
        $wallet = $this->getOrCreateWallet($sponsorId, 'club', 'USD');

        // Create ledger transaction
        $ledger = LedgerTransaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_from' => null, // System-generated income
            'user_to' => $sponsorId,
            'wallet_from_id' => null,
            'wallet_to_id' => $wallet->id,
            'type' => 'club_income',
            'amount' => $amount,
            'currency' => 'USD',
            'reference_id' => $sponsorId . '_level_' . $level,
            'description' => "Club income L{$level} completion bonus for sponsor {$sponsorId}"
        ]);

        // Update wallet balance
        Wallet::where('id', $wallet->id)->lockForUpdate()->increment('balance', $amount);

        // Create income record
        IncomeRecord::create([
            'user_id' => $sponsorId,
            'origin_user_id' => null, // System-generated income
            'user_package_id' => null,
            'income_config_id' => $config->id,
            'income_type' => 'club',
            'amount' => $amount,
            'currency' => 'USD',
            'status' => 'paid',
            'ledger_transaction_id' => $ledger->id,
            'reference_id' => $sponsorId . '_level_' . $level,
        ]);

        Log::info("ProcessClubJob: ✅ Level {$level} completed! Credited {$amount} to sponsor {$sponsorId}'s club wallet");
    }

    /**
     * Get club income amount from config
     */
    protected function getClubIncomeAmount(IncomeConfig $config, int $level): ?string
    {
        $meta = $config->metadata ?? [];

        // Check for fixed amount in metadata
        if (isset($meta['fixed_amount'])) {
            return (string) $meta['fixed_amount'];
        }

        // Check for level-specific amount
        if (isset($meta['level_amounts'][$level])) {
            return (string) $meta['level_amounts'][$level];
        }

        // Fallback to percentage (treat as fixed amount for club)
        if (!is_null($config->percentage)) {
            return (string) $config->percentage;
        }

        return null;
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
}
