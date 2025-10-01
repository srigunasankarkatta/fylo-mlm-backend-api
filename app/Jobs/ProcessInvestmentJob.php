<?php

namespace App\Jobs;

use App\Models\UserInvestment;
use App\Models\Wallet;
use App\Models\LedgerTransaction;
use App\Models\IncomeRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessInvestmentJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $userInvestmentId;

    // Precision for bc math
    protected int $scale = 8;

    public function __construct(int $userInvestmentId)
    {
        $this->userInvestmentId = $userInvestmentId;
    }

    public function handle()
    {
        $investment = UserInvestment::with(['user', 'investmentPlan', 'referrer'])
            ->find($this->userInvestmentId);

        if (!$investment) {
            Log::warning("ProcessInvestmentJob: Investment not found id {$this->userInvestmentId}");
            return;
        }

        // Idempotency: if already processed today, skip
        $today = now()->format('Y-m-d');
        $lastProcessed = $investment->metadata['last_processed_date'] ?? null;
        if ($lastProcessed === $today) {
            Log::info("ProcessInvestmentJob: Investment {$investment->id} already processed today");
            return;
        }

        DB::transaction(function () use ($investment, $today) {
            try {
                // 1) Process daily interest accrual for active investments
                if ($investment->isActive() && $investment->hasMatured() === false) {
                    $this->processDailyInterest($investment);
                }

                // 2) Process maturity for investments that have reached end date
                if ($investment->isActive() && $investment->hasMatured()) {
                    $this->processMaturity($investment);
                }

                // 3) Process referral commission if applicable
                if ($investment->referrer_id && $investment->referral_commission > 0) {
                    $this->processReferralCommission($investment);
                }

                // Update last processed date
                $metadata = $investment->metadata ?? [];
                $metadata['last_processed_date'] = $today;
                $investment->update(['metadata' => $metadata]);

                Log::info("ProcessInvestmentJob: Successfully processed investment {$investment->id}");
            } catch (\Throwable $e) {
                Log::error("ProcessInvestmentJob failed for investment {$investment->id}: {$e->getMessage()}");
                throw $e;
            }
        });
    }

    /**
     * Process daily interest accrual for active investments
     */
    protected function processDailyInterest(UserInvestment $investment)
    {
        $dailyInterest = $investment->getDailyInterestAmount();

        if ($dailyInterest <= 0) {
            return;
        }

        // Add to accrued interest
        $investment->addAccruedInterest($dailyInterest);

        // Credit user's main wallet
        $userWallet = Wallet::getOrCreate($investment->user_id, 'main', 'USD');
        $userWallet->addBalance($dailyInterest);

        // Create ledger transaction
        LedgerTransaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_from' => null, // Company
            'user_to' => $investment->user_id,
            'wallet_from_id' => null, // Company wallet
            'wallet_to_id' => $userWallet->id,
            'type' => 'investment_interest',
            'amount' => $dailyInterest,
            'currency' => 'USD',
            'reference_id' => "investment_{$investment->id}_daily_interest",
            'description' => "Daily interest for investment #{$investment->id}",
        ]);

        // Create income record
        IncomeRecord::create([
            'user_id' => $investment->user_id,
            'income_type' => 'investment_interest',
            'amount' => $dailyInterest,
            'reference_id' => "investment_{$investment->id}_daily_interest",
            'description' => "Daily interest for investment #{$investment->id}",
            'metadata' => [
                'investment_id' => $investment->id,
                'daily_profit_percent' => $investment->daily_profit_percent,
                'processed_at' => now()->toISOString(),
            ],
        ]);

        Log::info("ProcessInvestmentJob: Credited daily interest {$dailyInterest} to user {$investment->user_id} for investment {$investment->id}");
    }

    /**
     * Process investment maturity
     */
    protected function processMaturity(UserInvestment $investment)
    {
        // Mark investment as completed
        $investment->complete();

        // Calculate final payout (principal + accrued interest)
        $finalPayout = $investment->amount + $investment->accrued_interest;

        // Credit user's main wallet with final payout
        $userWallet = Wallet::getOrCreate($investment->user_id, 'main', 'USD');
        $userWallet->addBalance($finalPayout);

        // Update total payout
        $investment->addTotalPayout($finalPayout);

        // Create ledger transaction for final payout
        LedgerTransaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_from' => null, // Company
            'user_to' => $investment->user_id,
            'wallet_from_id' => null, // Company wallet
            'wallet_to_id' => $userWallet->id,
            'type' => 'investment_maturity',
            'amount' => $finalPayout,
            'currency' => 'USD',
            'reference_id' => "investment_{$investment->id}_maturity",
            'description' => "Investment maturity payout for investment #{$investment->id}",
        ]);

        // Create income record for final payout
        IncomeRecord::create([
            'user_id' => $investment->user_id,
            'income_type' => 'investment_maturity',
            'amount' => $finalPayout,
            'reference_id' => "investment_{$investment->id}_maturity",
            'description' => "Investment maturity payout for investment #{$investment->id}",
            'metadata' => [
                'investment_id' => $investment->id,
                'principal_amount' => $investment->amount,
                'accrued_interest' => $investment->accrued_interest,
                'matured_at' => now()->toISOString(),
            ],
        ]);

        Log::info("ProcessInvestmentJob: Processed maturity for investment {$investment->id} with payout {$finalPayout}");
    }

    /**
     * Process referral commission
     */
    protected function processReferralCommission(UserInvestment $investment)
    {
        if ($investment->referral_commission <= 0) {
            return;
        }

        // Credit referrer's main wallet
        $referrerWallet = Wallet::getOrCreate($investment->referrer_id, 'main', 'USD');
        $referrerWallet->addBalance($investment->referral_commission);

        // Create ledger transaction
        LedgerTransaction::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_from' => null, // Company
            'user_to' => $investment->referrer_id,
            'wallet_from_id' => null, // Company wallet
            'wallet_to_id' => $referrerWallet->id,
            'type' => 'investment_referral',
            'amount' => $investment->referral_commission,
            'currency' => 'USD',
            'reference_id' => "investment_{$investment->id}_referral",
            'description' => "Referral commission for investment #{$investment->id}",
        ]);

        // Create income record
        IncomeRecord::create([
            'user_id' => $investment->referrer_id,
            'income_type' => 'investment_referral',
            'amount' => $investment->referral_commission,
            'reference_id' => "investment_{$investment->id}_referral",
            'description' => "Referral commission for investment #{$investment->id}",
            'metadata' => [
                'investment_id' => $investment->id,
                'referrer_id' => $investment->referrer_id,
                'processed_at' => now()->toISOString(),
            ],
        ]);

        Log::info("ProcessInvestmentJob: Credited referral commission {$investment->referral_commission} to referrer {$investment->referrer_id} for investment {$investment->id}");
    }
}
