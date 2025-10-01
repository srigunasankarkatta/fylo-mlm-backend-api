<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvestmentPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvestmentPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get admin user for created_by/updated_by
        $adminUser = User::where('email', 'admin@fylo-mlm.com')->first();
        $adminId = $adminUser ? $adminUser->id : 1;

        // Clear existing investment plans
        DB::table('investment_plans')->truncate();

        $investmentPlans = [
            // Basic Plans - Low Risk, Low Return
            [
                'code' => 'BASIC-001',
                'name' => 'Starter Plan',
                'description' => 'Perfect for beginners. Low risk investment with guaranteed daily returns. Ideal for testing the platform and building confidence.',
                'min_amount' => 50.00,
                'max_amount' => 500.00,
                'daily_profit_percent' => 1.50,
                'duration_days' => 30,
                'referral_percent' => 5.00,
                'is_active' => true,
                'version' => 1,
                'metadata' => [
                    'risk_level' => 'low',
                    'category' => 'basic',
                    'popularity' => 'high',
                    'features' => ['guaranteed_returns', 'daily_payouts', 'low_minimum'],
                    'target_audience' => 'beginners',
                    'created_by_admin' => true,
                ],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],
            [
                'code' => 'BASIC-002',
                'name' => 'Growth Plan',
                'description' => 'Steady growth with moderate returns. Great for investors looking for consistent daily income with manageable risk.',
                'min_amount' => 100.00,
                'max_amount' => 1000.00,
                'daily_profit_percent' => 2.00,
                'duration_days' => 45,
                'referral_percent' => 7.50,
                'is_active' => true,
                'version' => 1,
                'metadata' => [
                    'risk_level' => 'low',
                    'category' => 'basic',
                    'popularity' => 'high',
                    'features' => ['steady_returns', 'extended_duration', 'higher_referral'],
                    'target_audience' => 'conservative_investors',
                    'created_by_admin' => true,
                ],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],

            // Premium Plans - Medium Risk, Medium Return
            [
                'code' => 'PREMIUM-001',
                'name' => 'Silver Investment',
                'description' => 'Premium investment plan with attractive returns. Perfect for experienced investors seeking higher daily profits.',
                'min_amount' => 500.00,
                'max_amount' => 5000.00,
                'daily_profit_percent' => 2.50,
                'duration_days' => 60,
                'referral_percent' => 10.00,
                'is_active' => true,
                'version' => 1,
                'metadata' => [
                    'risk_level' => 'medium',
                    'category' => 'premium',
                    'popularity' => 'very_high',
                    'features' => ['premium_returns', 'flexible_amounts', 'high_referral'],
                    'target_audience' => 'experienced_investors',
                    'created_by_admin' => true,
                ],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],
            [
                'code' => 'PREMIUM-002',
                'name' => 'Gold Investment',
                'description' => 'High-value investment plan with excellent returns. Designed for serious investors looking to maximize their profits.',
                'min_amount' => 1000.00,
                'max_amount' => 10000.00,
                'daily_profit_percent' => 3.00,
                'duration_days' => 90,
                'referral_percent' => 12.50,
                'is_active' => true,
                'version' => 1,
                'metadata' => [
                    'risk_level' => 'medium',
                    'category' => 'premium',
                    'popularity' => 'high',
                    'features' => ['high_returns', 'long_duration', 'maximum_referral'],
                    'target_audience' => 'serious_investors',
                    'created_by_admin' => true,
                ],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],

            // VIP Plans - High Risk, High Return
            [
                'code' => 'VIP-001',
                'name' => 'Platinum Elite',
                'description' => 'Exclusive VIP investment plan with maximum returns. Limited availability for high-net-worth investors.',
                'min_amount' => 5000.00,
                'max_amount' => 50000.00,
                'daily_profit_percent' => 3.50,
                'duration_days' => 120,
                'referral_percent' => 15.00,
                'is_active' => true,
                'version' => 1,
                'metadata' => [
                    'risk_level' => 'high',
                    'category' => 'vip',
                    'popularity' => 'medium',
                    'features' => ['maximum_returns', 'exclusive_access', 'premium_support'],
                    'target_audience' => 'high_net_worth',
                    'created_by_admin' => true,
                    'limited_availability' => true,
                ],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],
            [
                'code' => 'VIP-002',
                'name' => 'Diamond Supreme',
                'description' => 'Ultimate investment plan with exceptional returns. Reserved for our most valued investors with substantial capital.',
                'min_amount' => 10000.00,
                'max_amount' => null, // No upper limit
                'daily_profit_percent' => 4.00,
                'duration_days' => 180,
                'referral_percent' => 20.00,
                'is_active' => true,
                'version' => 1,
                'metadata' => [
                    'risk_level' => 'high',
                    'category' => 'vip',
                    'popularity' => 'low',
                    'features' => ['exceptional_returns', 'unlimited_amount', 'maximum_referral', 'exclusive_support'],
                    'target_audience' => 'ultra_high_net_worth',
                    'created_by_admin' => true,
                    'exclusive' => true,
                ],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],

            // Special Plans - Unique Features
            [
                'code' => 'SPECIAL-001',
                'name' => 'Weekend Warrior',
                'description' => 'Short-term investment plan perfect for weekend traders. Quick returns with moderate risk.',
                'min_amount' => 200.00,
                'max_amount' => 2000.00,
                'daily_profit_percent' => 2.25,
                'duration_days' => 14,
                'referral_percent' => 8.00,
                'is_active' => true,
                'version' => 1,
                'metadata' => [
                    'risk_level' => 'medium',
                    'category' => 'special',
                    'popularity' => 'medium',
                    'features' => ['quick_returns', 'short_duration', 'weekend_friendly'],
                    'target_audience' => 'active_traders',
                    'created_by_admin' => true,
                ],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],
            [
                'code' => 'SPECIAL-002',
                'name' => 'Holiday Bonus',
                'description' => 'Limited-time holiday investment plan with bonus returns. Available only during special promotions.',
                'min_amount' => 300.00,
                'max_amount' => 3000.00,
                'daily_profit_percent' => 2.75,
                'duration_days' => 21,
                'referral_percent' => 9.00,
                'is_active' => true,
                'version' => 1,
                'metadata' => [
                    'risk_level' => 'medium',
                    'category' => 'special',
                    'popularity' => 'high',
                    'features' => ['bonus_returns', 'limited_time', 'holiday_promotion'],
                    'target_audience' => 'promotion_seekers',
                    'created_by_admin' => true,
                    'limited_time' => true,
                ],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],

            // Corporate Plans - Business Focused
            [
                'code' => 'CORP-001',
                'name' => 'Business Builder',
                'description' => 'Designed for business owners and entrepreneurs. Higher minimum investment with business-focused returns.',
                'min_amount' => 2000.00,
                'max_amount' => 20000.00,
                'daily_profit_percent' => 2.80,
                'duration_days' => 75,
                'referral_percent' => 11.00,
                'is_active' => true,
                'version' => 1,
                'metadata' => [
                    'risk_level' => 'medium',
                    'category' => 'corporate',
                    'popularity' => 'medium',
                    'features' => ['business_focused', 'higher_minimum', 'corporate_support'],
                    'target_audience' => 'business_owners',
                    'created_by_admin' => true,
                ],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],

            // Inactive Plans - For Testing
            [
                'code' => 'TEST-001',
                'name' => 'Test Plan (Inactive)',
                'description' => 'This is a test plan that is currently inactive. Used for testing purposes only.',
                'min_amount' => 10.00,
                'max_amount' => 100.00,
                'daily_profit_percent' => 1.00,
                'duration_days' => 7,
                'referral_percent' => 2.00,
                'is_active' => false,
                'version' => 1,
                'metadata' => [
                    'risk_level' => 'low',
                    'category' => 'test',
                    'popularity' => 'none',
                    'features' => ['testing_only'],
                    'target_audience' => 'developers',
                    'created_by_admin' => true,
                    'testing' => true,
                ],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],
        ];

        // Create investment plans
        foreach ($investmentPlans as $planData) {
            InvestmentPlan::create($planData);
        }

        $this->command->info('✅ Investment plans seeded successfully!');
        $this->command->info('📊 Created ' . count($investmentPlans) . ' investment plans');

        // Display summary
        $this->command->info("\n📋 Investment Plans Summary:");
        $this->command->info("==========================");

        $activePlans = InvestmentPlan::where('is_active', true)->count();
        $inactivePlans = InvestmentPlan::where('is_active', false)->count();

        $this->command->info("Active Plans: {$activePlans}");
        $this->command->info("Inactive Plans: {$inactivePlans}");

        // Show plan categories
        $categories = InvestmentPlan::select('metadata->category as category')
            ->where('is_active', true)
            ->distinct()
            ->pluck('category')
            ->toArray();

        $this->command->info("Categories: " . implode(', ', $categories));

        // Show price ranges
        $minPrice = InvestmentPlan::where('is_active', true)->min('min_amount');
        $maxPrice = InvestmentPlan::where('is_active', true)->max('max_amount');

        $this->command->info("Price Range: $" . number_format($minPrice, 2) . " - $" . number_format($maxPrice, 2));

        // Show profit ranges
        $minProfit = InvestmentPlan::where('is_active', true)->min('daily_profit_percent');
        $maxProfit = InvestmentPlan::where('is_active', true)->max('daily_profit_percent');

        $this->command->info("Daily Profit Range: " . number_format($minProfit, 2) . "% - " . number_format($maxProfit, 2) . "%");

        $this->command->info("\n🚀 Investment plans are ready for use!");
    }
}
