<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $packages = [
            [
                'code' => 'BRONZE',
                'name' => 'Bronze',
                'level_number' => 1,
                'price' => 100,
                'description' => 'Entry level package for beginners',
                'is_active' => true,
            ],
            [
                'code' => 'SILVER',
                'name' => 'Silver',
                'level_number' => 2,
                'price' => 200,
                'description' => 'Silver level package with enhanced benefits',
                'is_active' => true,
            ],
            [
                'code' => 'GOLD',
                'name' => 'Gold',
                'level_number' => 3,
                'price' => 300,
                'description' => 'Gold level package with premium features',
                'is_active' => true,
            ],
            [
                'code' => 'PLATINUM',
                'name' => 'Platinum',
                'level_number' => 4,
                'price' => 400,
                'description' => 'Platinum level package with exclusive benefits',
                'is_active' => true,
            ],
            [
                'code' => 'AVAX',
                'name' => 'Avax',
                'level_number' => 5,
                'price' => 500,
                'description' => 'Avax level package with advanced features',
                'is_active' => true,
            ],
            [
                'code' => 'DOGE',
                'name' => 'Doge',
                'level_number' => 6,
                'price' => 600,
                'description' => 'Doge level package with special rewards',
                'is_active' => true,
            ],
            [
                'code' => 'XRP',
                'name' => 'XRP Ripple',
                'level_number' => 7,
                'price' => 700,
                'description' => 'XRP Ripple level package with ripple benefits',
                'is_active' => true,
            ],
            [
                'code' => 'SOLANA',
                'name' => 'Solana',
                'level_number' => 8,
                'price' => 800,
                'description' => 'Solana level package with high-speed benefits',
                'is_active' => true,
            ],
            [
                'code' => 'ETH',
                'name' => 'Ethereum',
                'level_number' => 9,
                'price' => 900,
                'description' => 'Ethereum level package with smart contract benefits',
                'is_active' => true,
            ],
            [
                'code' => 'NBNB',
                'name' => 'NBNB',
                'level_number' => 10,
                'price' => 1000,
                'description' => 'NBNB level package - the highest tier with maximum benefits',
                'is_active' => true,
            ],
        ];

        foreach ($packages as $packageData) {
            Package::firstOrCreate(
                ['code' => $packageData['code']],
                $packageData
            );
        }

        $this->command->info('Packages seeded successfully!');
        $this->command->info('Created ' . count($packages) . ' packages from Bronze to NBNB');
    }
}
