<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $defaults = [
            [
                'key' => 'max_direct_children',
                'value' => ['value' => 4, 'min' => 1, 'max' => 10],
                'description' => 'Maximum number of direct child nodes per user in the MLM tree'
            ],
            [
                'key' => 'default_currency',
                'value' => ['value' => 'USD', 'supported' => ['USD', 'EUR', 'GBP', 'CAD']],
                'description' => 'Default currency for the platform'
            ],
            [
                'key' => 'referral_enabled',
                'value' => ['value' => true],
                'description' => 'Enable or disable referral system at registration'
            ],
            [
                'key' => 'income_config_cache_ttl',
                'value' => ['value' => 3600, 'unit' => 'seconds'],
                'description' => 'TTL in seconds for income configs cache'
            ],
            [
                'key' => 'min_package_amount',
                'value' => ['value' => 100, 'currency' => 'USD'],
                'description' => 'Minimum package purchase amount'
            ],
            [
                'key' => 'max_package_amount',
                'value' => ['value' => 10000, 'currency' => 'USD'],
                'description' => 'Maximum package purchase amount'
            ],
            [
                'key' => 'payout_processing_enabled',
                'value' => ['value' => true],
                'description' => 'Enable or disable automatic payout processing'
            ],
            [
                'key' => 'payout_minimum_amount',
                'value' => ['value' => 50, 'currency' => 'USD'],
                'description' => 'Minimum amount required for payout request'
            ],
            [
                'key' => 'payout_processing_fee',
                'value' => ['value' => 2.5, 'unit' => 'percentage'],
                'description' => 'Processing fee for payouts (percentage)'
            ],
            [
                'key' => 'club_matrix_levels',
                'value' => ['value' => 10, 'min' => 1, 'max' => 20],
                'description' => 'Number of levels in the club matrix'
            ],
            [
                'key' => 'autopool_levels',
                'value' => ['value' => 8, 'min' => 1, 'max' => 15],
                'description' => 'Number of sub-levels in autopool system'
            ],
            [
                'key' => 'user_registration_enabled',
                'value' => ['value' => true],
                'description' => 'Enable or disable new user registration'
            ],
            [
                'key' => 'email_verification_required',
                'value' => ['value' => true],
                'description' => 'Require email verification for new users'
            ],
            [
                'key' => 'phone_verification_required',
                'value' => ['value' => false],
                'description' => 'Require phone verification for new users'
            ],
            [
                'key' => 'maintenance_mode',
                'value' => ['value' => false, 'message' => ''],
                'description' => 'Enable maintenance mode for the platform'
            ],
            [
                'key' => 'site_name',
                'value' => ['value' => 'Fylo MLM Platform'],
                'description' => 'Name of the MLM platform'
            ],
            [
                'key' => 'site_description',
                'value' => ['value' => 'Advanced MLM Platform with Multiple Income Streams'],
                'description' => 'Description of the MLM platform'
            ],
            [
                'key' => 'admin_email',
                'value' => ['value' => 'admin@fylomlm.com'],
                'description' => 'Primary admin email address'
            ],
            [
                'key' => 'support_email',
                'value' => ['value' => 'support@fylomlm.com'],
                'description' => 'Support email address'
            ],
            [
                'key' => 'max_login_attempts',
                'value' => ['value' => 5, 'lockout_duration' => 900],
                'description' => 'Maximum login attempts before account lockout (in seconds)'
            ],
            [
                'key' => 'session_timeout',
                'value' => ['value' => 3600, 'unit' => 'seconds'],
                'description' => 'Session timeout duration in seconds'
            ],
            [
                'key' => 'backup_frequency',
                'value' => ['value' => 'daily', 'options' => ['hourly', 'daily', 'weekly', 'monthly']],
                'description' => 'Database backup frequency'
            ],
            [
                'key' => 'audit_log_retention_days',
                'value' => ['value' => 365, 'min' => 30, 'max' => 1095],
                'description' => 'Number of days to retain audit logs'
            ],
            [
                'key' => 'income_calculation_precision',
                'value' => ['value' => 8, 'min' => 2, 'max' => 18],
                'description' => 'Decimal precision for income calculations'
            ],
            [
                'key' => 'auto_approve_packages',
                'value' => ['value' => false],
                'description' => 'Automatically approve package purchases'
            ],
            [
                'key' => 'commission_payout_schedule',
                'value' => ['value' => 'daily', 'options' => ['hourly', 'daily', 'weekly', 'monthly']],
                'description' => 'Schedule for commission payouts'
            ],
            [
                'key' => 'referral_bonus_percentage',
                'value' => ['value' => 10, 'unit' => 'percentage'],
                'description' => 'Referral bonus percentage for new signups'
            ],
            [
                'key' => 'matching_bonus_percentage',
                'value' => ['value' => 5, 'unit' => 'percentage'],
                'description' => 'Matching bonus percentage for team building'
            ],
            [
                'key' => 'leadership_bonus_threshold',
                'value' => ['value' => 1000, 'currency' => 'USD'],
                'description' => 'Minimum team volume for leadership bonus eligibility'
            ],
        ];

        foreach ($defaults as $settingData) {
            SystemSetting::updateOrCreate(
                ['key' => $settingData['key']],
                $settingData
            );
        }

        $this->command->info('System settings seeded successfully!');
        $this->command->info('Created ' . count($defaults) . ' system settings');

        // Display summary
        $this->command->info('Settings summary:');
        $this->command->info('- Platform Settings: ' . SystemSetting::whereIn('key', ['site_name', 'site_description', 'default_currency'])->count());
        $this->command->info('- MLM Settings: ' . SystemSetting::whereIn('key', ['max_direct_children', 'club_matrix_levels', 'autopool_levels'])->count());
        $this->command->info('- Financial Settings: ' . SystemSetting::whereIn('key', ['min_package_amount', 'max_package_amount', 'payout_minimum_amount'])->count());
        $this->command->info('- Security Settings: ' . SystemSetting::whereIn('key', ['max_login_attempts', 'session_timeout', 'audit_log_retention_days'])->count());
    }
}
