<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingsSeeder extends Seeder
{
    public function run()
    {
        $defaults = [
            [
                'key' => 'max_direct_children',
                'value' => ['value' => 4],
                'description' => 'Maximum direct child nodes per user'
            ],
            [
                'key' => 'default_currency',
                'value' => ['value' => 'USD'],
                'description' => 'Default currency for system wallets'
            ],
            [
                'key' => 'referral_enabled',
                'value' => ['value' => true],
                'description' => 'Allow registration using referral codes'
            ],
            [
                'key' => 'income_config_cache_ttl',
                'value' => ['value' => 3600],
                'description' => 'TTL (seconds) for cached income configs'
            ],
            [
                'key' => 'sequential_package_purchase',
                'value' => ['value' => true],
                'description' => 'Require users to buy packages sequentially (1 → 2 → 3...)'
            ],
            [
                'key' => 'min_withdrawal_amount',
                'value' => ['value' => 50.0],
                'description' => 'Minimum withdrawal amount for payouts (currency units)'
            ],
            [
                'key' => 'kyc_required_for_withdrawal',
                'value' => ['value' => false],
                'description' => 'Require KYC verification for withdrawals'
            ],
        ];

        foreach ($defaults as $s) {
            SystemSetting::updateOrCreate(
                ['key' => $s['key']],
                [
                    'value' => $s['value'],
                    'description' => $s['description']
                ]
            );
        }
    }
}
