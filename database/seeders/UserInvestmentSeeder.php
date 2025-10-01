<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserInvestment;
use App\Models\InvestmentPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserInvestmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing user investments
        DB::table('user_investments')->truncate();

        // Get investment plans
        $plans = InvestmentPlan::where('is_active', true)->get();
        if ($plans->isEmpty()) {
            $this->command->warn('No active investment plans found. Please run InvestmentPlanSeeder first.');
            return;
        }

        // Get users (excluding admin)
        $users = User::where('email', '!=', 'admin@fylo-mlm.com')->get();
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please create some users first.');
            return;
        }

        $investments = [];
        $now = now();

        // Create investments for each user
        foreach ($users as $user) {
            // Each user gets 1-3 random investments
            $investmentCount = rand(1, 3);

            for ($i = 0; $i < $investmentCount; $i++) {
                $plan = $plans->random();

                // Generate random amount within plan limits
                $minAmount = $plan->min_amount;
                $maxAmount = $plan->max_amount ?? ($plan->min_amount * 10); // If no max, use 10x min
                $amount = rand($minAmount * 100, $maxAmount * 100) / 100; // Random amount with 2 decimal places

                // Calculate referral commission
                $referralCommission = $amount * ($plan->referral_percent / 100);

                // Random status distribution
                $statusOptions = ['pending', 'active', 'completed', 'cancelled'];
                $statusWeights = [20, 50, 25, 5]; // 20% pending, 50% active, 25% completed, 5% cancelled
                $status = $this->weightedRandom($statusOptions, $statusWeights);

                // Set dates based on status
                $investedAt = $now->copy()->subDays(rand(1, 90));
                $startAt = null;
                $endAt = null;
                $maturedAt = null;
                $accruedInterest = 0;
                $totalPayout = 0;

                if (in_array($status, ['active', 'completed'])) {
                    $startAt = $investedAt->copy()->addDays(rand(0, 5)); // Started 0-5 days after investment
                    $endAt = $startAt->copy()->addDays($plan->duration_days);

                    if ($status === 'completed') {
                        $maturedAt = $endAt->copy()->addDays(rand(0, 3)); // Completed 0-3 days after maturity
                        $accruedInterest = $amount * ($plan->daily_profit_percent / 100) * $plan->duration_days;
                        $totalPayout = $amount + $accruedInterest;
                    } else {
                        // Active investment - calculate partial interest
                        $elapsedDays = $now->diffInDays($startAt);
                        $accruedInterest = $amount * ($plan->daily_profit_percent / 100) * min($elapsedDays, $plan->duration_days);
                    }
                }

                // Random referrer (50% chance)
                $referrerId = null;
                if (rand(1, 100) <= 50 && $users->count() > 1) {
                    $referrerId = $users->where('id', '!=', $user->id)->random()->id;
                }

                $investments[] = [
                    'user_id' => $user->id,
                    'investment_plan_id' => $plan->id,
                    'amount' => $amount,
                    'daily_profit_percent' => $plan->daily_profit_percent,
                    'duration_days' => $plan->duration_days,
                    'invested_at' => $investedAt,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'matured_at' => $maturedAt,
                    'accrued_interest' => $accruedInterest,
                    'total_payout' => $totalPayout,
                    'status' => $status,
                    'referrer_id' => $referrerId,
                    'referral_commission' => $referrerId ? $referralCommission : 0,
                    'metadata' => [
                        'seeded' => true,
                        'created_via' => 'seeder',
                        'test_data' => true,
                        'random_amount' => true,
                    ],
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'created_at' => $investedAt,
                    'updated_at' => $now,
                ];
            }
        }

        // Insert all investments
        foreach ($investments as $investment) {
            UserInvestment::create($investment);
        }

        $this->command->info('✅ User investments seeded successfully!');
        $this->command->info('📊 Created ' . count($investments) . ' user investments');

        // Display summary
        $this->command->info("\n📋 User Investments Summary:");
        $this->command->info("===========================");

        $statusCounts = UserInvestment::select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        foreach ($statusCounts as $status => $count) {
            $this->command->info(ucfirst($status) . ": {$count}");
        }

        $totalAmount = UserInvestment::sum('amount');
        $totalInterest = UserInvestment::sum('accrued_interest');
        $totalPayouts = UserInvestment::sum('total_payout');
        $totalReferrals = UserInvestment::sum('referral_commission');

        $this->command->info("\n💰 Financial Summary:");
        $this->command->info("Total Invested: $" . number_format($totalAmount, 2));
        $this->command->info("Total Interest: $" . number_format($totalInterest, 2));
        $this->command->info("Total Payouts: $" . number_format($totalPayouts, 2));
        $this->command->info("Total Referrals: $" . number_format($totalReferrals, 2));

        // Show plan distribution
        $planCounts = UserInvestment::join('investment_plans', 'user_investments.investment_plan_id', '=', 'investment_plans.id')
            ->select('investment_plans.name')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('investment_plans.name')
            ->orderBy('count', 'desc')
            ->get();

        $this->command->info("\n📈 Plan Distribution:");
        foreach ($planCounts as $plan) {
            $this->command->info("{$plan->name}: {$plan->count} investments");
        }

        $this->command->info("\n🚀 User investments are ready for testing!");
    }

    /**
     * Weighted random selection
     */
    private function weightedRandom(array $options, array $weights): string
    {
        $totalWeight = array_sum($weights);
        $random = rand(1, $totalWeight);

        $currentWeight = 0;
        foreach ($options as $index => $option) {
            $currentWeight += $weights[$index];
            if ($random <= $currentWeight) {
                return $option;
            }
        }

        return $options[0]; // Fallback
    }
}
