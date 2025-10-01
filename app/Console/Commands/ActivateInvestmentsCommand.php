<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserInvestment;
use App\Models\Wallet;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivateInvestmentsCommand extends Command
{
    protected $signature = 'investments:activate {--user-id= : Activate investments for specific user} {--investment-id= : Activate specific investment}';
    protected $description = 'Activate pending investments (process payment and start earning)';

    public function handle()
    {
        $this->info("🚀 Activating investments...");

        try {
            $query = UserInvestment::pending();

            if ($this->option('user-id')) {
                $query->where('user_id', $this->option('user-id'));
            }

            if ($this->option('investment-id')) {
                $query->where('id', $this->option('investment-id'));
            }

            $investments = $query->with(['user', 'investmentPlan'])->get();

            $this->info("Found {$investments->count()} pending investments");

            if ($investments->isEmpty()) {
                $this->info("No pending investments to activate");
                return 0;
            }

            $activated = 0;
            $failed = 0;

            foreach ($investments as $investment) {
                try {
                    $this->activateInvestment($investment);
                    $activated++;
                    $this->info("✅ Activated investment #{$investment->id} for user {$investment->user->name}");
                } catch (\Exception $e) {
                    $failed++;
                    $this->error("❌ Failed to activate investment #{$investment->id}: " . $e->getMessage());
                }
            }

            $this->info("📊 Activation Summary:");
            $this->info("   Activated: {$activated}");
            $this->info("   Failed: {$failed}");

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Investment activation failed: " . $e->getMessage());
            Log::error("ActivateInvestmentsCommand failed: " . $e->getMessage());
            return 1;
        }
    }

    protected function activateInvestment(UserInvestment $investment)
    {
        DB::transaction(function () use ($investment) {
            // 1) Process payment (deduct from user's main wallet)
            $userWallet = Wallet::getOrCreate($investment->user_id, 'main', 'USD');

            if ($userWallet->balance < $investment->amount) {
                throw new \Exception("Insufficient wallet balance. Required: {$investment->amount}, Available: {$userWallet->balance}");
            }

            // Deduct amount from user's wallet
            $userWallet->subtractBalance($investment->amount);

            // 2) Create ledger transaction for payment
            LedgerTransaction::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'user_from' => $investment->user_id,
                'user_to' => null, // Company
                'wallet_from_id' => $userWallet->id,
                'wallet_to_id' => null, // Company wallet
                'type' => 'investment_payment',
                'amount' => $investment->amount,
                'currency' => 'USD',
                'reference_id' => "investment_{$investment->id}_payment",
                'description' => "Investment payment for {$investment->investmentPlan->name}",
            ]);

            // 3) Process referral commission if applicable
            if ($investment->referrer_id && $investment->referral_commission > 0) {
                $this->processReferralCommission($investment);
            }

            // 4) Activate the investment
            $investment->activate();

            Log::info("Activated investment #{$investment->id} for user {$investment->user_id}");
        });
    }

    protected function processReferralCommission(UserInvestment $investment)
    {
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

        Log::info("Processed referral commission {$investment->referral_commission} for investment #{$investment->id}");
    }
}
