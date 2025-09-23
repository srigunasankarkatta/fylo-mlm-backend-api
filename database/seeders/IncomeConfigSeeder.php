<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IncomeConfig;
use App\Models\Package;
use Carbon\Carbon;

class IncomeConfigSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        // 1) Level income (fixed 0.5 unit per ancestor)
        IncomeConfig::updateOrCreate(
            [
                'income_type' => 'level',
                'package_id' => null,
                'level' => null,
                'sub_level' => null
            ],
            [
                'name' => 'Level Income - fixed 0.5',
                'percentage' => 0.0, // 0% since we use fixed amount
                'metadata' => ['fixed_amount' => 0.5],
                'is_active' => true,
                'version' => 1,
                'effective_from' => $now
            ]
        );

        // 2) Fasttrack: seed a package-specific fasttrack percentage and company_share
        // Default: 5% to upline, 2% company allocation. Admin can tune per package later.
        $packages = Package::all();
        foreach ($packages as $pkg) {
            IncomeConfig::updateOrCreate(
                [
                    'income_type' => 'fasttrack',
                    'package_id' => $pkg->id,
                    'level' => null,
                    'sub_level' => null
                ],
                [
                    'name' => "Fasttrack - package {$pkg->code}",
                    'percentage' => 5.0, // admin enters 5 meaning 5% (job normalizes)
                    'metadata' => ['company_share' => 2.0], // 2% goes to Total Company Income by default
                    'is_active' => true,
                    'version' => 1,
                    'effective_from' => $now
                ]
            );
        }

        // 3) Company Deduction (global fallback)
        IncomeConfig::updateOrCreate(
            [
                'income_type' => 'fasttrack',
                'package_id' => null,
                'level' => null,
                'sub_level' => null,
                'name' => 'Company Deduction (global)'
            ],
            [
                'name' => 'Company Deduction (global)',
                'percentage' => 0.0, // 0% since we use company_share
                'metadata' => ['company_share' => 2.0],
                'is_active' => true,
                'version' => 1,
                'effective_from' => $now
            ]
        );

        // 4) Club income (level payouts)
        // WARNING: these escalate quickly. We seed by default as per pattern: L1=4, L2=16, L3=64 ...
        // Admin should confirm these amounts; an alternative is much smaller fixed bonuses.
        for ($lvl = 1; $lvl <= 10; $lvl++) {
            $fixed = pow(4, $lvl); // 4^level (L1=4, L2=16, ... L10=1,048,576)
            IncomeConfig::updateOrCreate(
                [
                    'income_type' => 'club',
                    'package_id' => null,
                    'level' => $lvl,
                    'sub_level' => null
                ],
                [
                    'name' => "Club Income - Level {$lvl}",
                    'percentage' => 0.0, // 0% since we use fixed amount
                    'metadata' => ['fixed_amount' => $fixed],
                    'is_active' => true,
                    'version' => 1,
                    'effective_from' => $now
                ]
            );
        }

        // 5) AUTOPOOL (sample defaults)
        // IMPORTANT: Replace these default autopool percentages with your exact business values.
        // The code below creates for each package, for pool levels 1..10, and sublevels 1..8,
        // using a sensible distribution per sublevel (sums to 100 across sublevels).
        //
        // Example sublevel split (per level):
        // [10, 12, 14, 14, 14, 12, 12, 12]  => sums to 100
        // You may want different splits per package. Replace as needed.
        $defaultSublevelPercents = [10, 12, 14, 14, 14, 12, 12, 12];

        foreach ($packages as $pkg) {
            for ($poolLevel = 1; $poolLevel <= 10; $poolLevel++) {
                for ($sub = 1; $sub <= 8; $sub++) {
                    $pct = $defaultSublevelPercents[$sub - 1];
                    IncomeConfig::updateOrCreate(
                        [
                            'income_type' => 'autopool',
                            'package_id' => $pkg->id,
                            'level' => $poolLevel,
                            'sub_level' => $sub
                        ],
                        [
                            'name' => "AutoPool - Package {$pkg->code} L{$poolLevel}S{$sub}",
                            'percentage' => $pct, // admin-entered % (e.g. 10 means 10%)
                            'metadata' => [],
                            'is_active' => true,
                            'version' => 1,
                            'effective_from' => $now
                        ]
                    );
                } // sub
            } // pool level
        } // package

        // NOTE: The autopool values above are *default placeholders*. Your original autopool tables include very large numbers.
        // If those are the intended values, replace the $defaultSublevelPercents or the inside of this seeder with the exact percentages
        // you want. The admin panel (income-configs) can also be used to edit them later.
    }
}
