<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IncomeConfig;
use App\Models\Package;

class IncomeConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Get packages for reference
        $packages = Package::all()->keyBy('id');

        $defaults = [
            // Global Level Income Configurations
            [
                'name' => 'Level Income - Bronze',
                'income_type' => 'level',
                'package_id' => $packages->get(1)->id ?? 1, // Bronze
                'level' => 1,
                'percentage' => 0.05, // 5%
                'is_active' => true,
            ],
            [
                'name' => 'Level Income - Silver',
                'income_type' => 'level',
                'package_id' => $packages->get(2)->id ?? 2, // Silver
                'level' => 1,
                'percentage' => 0.06, // 6%
                'is_active' => true,
            ],
            [
                'name' => 'Level Income - Gold',
                'income_type' => 'level',
                'package_id' => $packages->get(3)->id ?? 3, // Gold
                'level' => 1,
                'percentage' => 0.07, // 7%
                'is_active' => true,
            ],

            // Global Fasttrack Income Configurations
            [
                'name' => 'Fasttrack Income - Global',
                'income_type' => 'fasttrack',
                'package_id' => null, // Global
                'percentage' => 0.05, // 5%
                'is_active' => true,
            ],
            [
                'name' => 'Company Deduction - Fasttrack',
                'income_type' => 'fasttrack',
                'package_id' => null, // Global
                'percentage' => 0.02, // 2%
                'is_active' => true,
            ],

            // Auto Pool Income Configurations
            [
                'name' => 'Auto Pool Income - Bronze L1-S1',
                'income_type' => 'autopool',
                'package_id' => $packages->get(1)->id ?? 1, // Bronze
                'level' => 1,
                'sub_level' => 1,
                'percentage' => 0.01, // 1%
                'is_active' => true,
            ],
            [
                'name' => 'Auto Pool Income - Bronze L1-S2',
                'income_type' => 'autopool',
                'package_id' => $packages->get(1)->id ?? 1, // Bronze
                'level' => 1,
                'sub_level' => 2,
                'percentage' => 0.015, // 1.5%
                'is_active' => true,
            ],
            [
                'name' => 'Auto Pool Income - Silver L1-S1',
                'income_type' => 'autopool',
                'package_id' => $packages->get(2)->id ?? 2, // Silver
                'level' => 1,
                'sub_level' => 1,
                'percentage' => 0.012, // 1.2%
                'is_active' => true,
            ],

            // Club Income Configurations
            [
                'name' => 'Club Income - Level 1',
                'income_type' => 'club',
                'package_id' => null, // Global
                'level' => 1,
                'percentage' => 0.03, // 3%
                'is_active' => true,
            ],
            [
                'name' => 'Club Income - Level 2',
                'income_type' => 'club',
                'package_id' => null, // Global
                'level' => 2,
                'percentage' => 0.025, // 2.5%
                'is_active' => true,
            ],
            [
                'name' => 'Club Income - Level 3',
                'income_type' => 'club',
                'package_id' => null, // Global
                'level' => 3,
                'percentage' => 0.02, // 2%
                'is_active' => true,
            ],

            // Other Income Configurations
            [
                'name' => 'Referral Bonus',
                'income_type' => 'other',
                'package_id' => null, // Global
                'percentage' => 0.01, // 1%
                'is_active' => true,
            ],
            [
                'name' => 'Matching Bonus',
                'income_type' => 'other',
                'package_id' => null, // Global
                'percentage' => 0.005, // 0.5%
                'is_active' => true,
            ],
        ];

        foreach ($defaults as $configData) {
            // Create unique key for firstOrCreate
            $uniqueKey = [
                'income_type' => $configData['income_type'],
                'package_id' => $configData['package_id'] ?? null,
                'level' => $configData['level'] ?? null,
                'sub_level' => $configData['sub_level'] ?? null,
            ];

            IncomeConfig::firstOrCreate($uniqueKey, $configData);
        }

        $this->command->info('Income configurations seeded successfully!');
        $this->command->info('Created ' . count($defaults) . ' income configurations');

        // Display summary
        $this->command->info('Configuration summary:');
        $this->command->info('- Level Income: ' . IncomeConfig::byType('level')->count());
        $this->command->info('- Fasttrack Income: ' . IncomeConfig::byType('fasttrack')->count());
        $this->command->info('- Auto Pool Income: ' . IncomeConfig::byType('autopool')->count());
        $this->command->info('- Club Income: ' . IncomeConfig::byType('club')->count());
        $this->command->info('- Other Income: ' . IncomeConfig::byType('other')->count());
    }
}
