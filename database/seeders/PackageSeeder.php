<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;

class PackageSeeder extends Seeder
{
    public function run()
    {
        $packages = [
            ['code' => 'BRONZE', 'name' => 'Bronze', 'level_number' => 1, 'price' => '100.00'],
            ['code' => 'SILVER', 'name' => 'Silver', 'level_number' => 2, 'price' => '200.00'],
            ['code' => 'GOLD', 'name' => 'Gold', 'level_number' => 3, 'price' => '300.00'],
            ['code' => 'PLATINUM', 'name' => 'Platinum', 'level_number' => 4, 'price' => '400.00'],
            ['code' => 'AVAX', 'name' => 'Avax', 'level_number' => 5, 'price' => '500.00'],
            ['code' => 'DOGE', 'name' => 'Doge', 'level_number' => 6, 'price' => '600.00'],
            ['code' => 'XRP', 'name' => 'XRP Ripple', 'level_number' => 7, 'price' => '700.00'],
            ['code' => 'SOLANA', 'name' => 'Solana', 'level_number' => 8, 'price' => '800.00'],
            ['code' => 'ETH', 'name' => 'Ethereum', 'level_number' => 9, 'price' => '900.00'],
            ['code' => 'NBNB', 'name' => 'NBNB', 'level_number' => 10, 'price' => '1000.00'],
        ];

        foreach ($packages as $pkg) {
            Package::updateOrCreate(
                ['code' => $pkg['code']],
                [
                    'name' => $pkg['name'],
                    'price' => $pkg['price'],
                    'level_number' => $pkg['level_number'],
                    'is_active' => true,
                    'description' => $pkg['name'] . ' package'
                ]
            );
        }
    }
}
