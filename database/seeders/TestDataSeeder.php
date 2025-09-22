<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserTree;
use App\Models\IncomeConfig;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create root user (admin)
        $root = User::where('email', 'admin@example.com')->first();
        if (!$root) {
            $root = User::create([
                'name' => 'Root Admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'phone' => '1234567890'
            ]);
        }

        // Create user tree for root
        $rootTree = UserTree::firstOrCreate(
            ['user_id' => $root->id],
            [
                'parent_id' => null,
                'position' => 1,
                'path' => '/',
                'depth' => 0
            ]
        );

        // Create test user tree
        $testUser = User::where('email', 'test2@example.com')->first();
        if ($testUser) {
            $testTree = UserTree::firstOrCreate(
                ['user_id' => $testUser->id],
                [
                    'parent_id' => $rootTree->id,
                    'position' => 1,
                    'path' => '/' . $root->id . '/',
                    'depth' => 1
                ]
            );
        }

        // Create income configs for testing
        IncomeConfig::firstOrCreate(
            ['income_type' => 'fasttrack', 'package_id' => 1],
            [
                'name' => 'Fasttrack 10%',
                'percentage' => 10,
                'is_active' => true,
                'metadata' => ['description' => '10% fasttrack for package 1']
            ]
        );

        IncomeConfig::firstOrCreate(
            ['income_type' => 'level'],
            [
                'name' => 'Level Income',
                'percentage' => 0.5,
                'is_active' => true,
                'metadata' => ['fixed_amount' => 0.5, 'description' => '0.5 per level']
            ]
        );

        IncomeConfig::firstOrCreate(
            ['income_type' => 'fasttrack', 'name' => 'Company Allocation'],
            [
                'percentage' => 5,
                'is_active' => true,
                'metadata' => ['company_share' => 5, 'description' => '5% company allocation']
            ]
        );

        $this->command->info('Test data created successfully!');
    }
}
