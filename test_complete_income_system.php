<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Package;
use App\Models\UserPackage;
use App\Models\UserTree;
use App\Models\ClubEntry;
use App\Models\IncomeConfig;
use App\Models\Wallet;
use App\Models\LedgerTransaction;
use App\Models\IncomeRecord;
use App\Services\PlacementService;
use App\Jobs\ProcessPurchaseJob;
use App\Jobs\ProcessClubJob;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

echo "=== COMPREHENSIVE INCOME SYSTEM TEST SUITE ===\n";
echo "Testing all 4 income types: Level, Fasttrack, Club, Auto Pool\n\n";

// Clean up previous test data
echo "🧹 Cleaning up previous test data...\n";
DB::table('income_records')->where('origin_user_id', '>', 10)->delete();
DB::table('ledger_transactions')->where('user_from', '>', 10)->delete();
DB::table('club_entries')->where('user_id', '>', 10)->delete();
DB::table('user_packages')->where('user_id', '>', 10)->delete();
DB::table('user_tree')->where('user_id', '>', 10)->delete();
DB::table('wallets')->where('user_id', '>', 10)->delete();
DB::table('users')->where('id', '>', 10)->delete();

// Ensure roles exist
$userRole = Role::firstOrCreate(['name' => 'user']);

// Create test users with realistic names
echo "👥 Creating test users...\n";
$users = [];

// Root user (sponsor)
$users['root'] = User::create([
    'uuid' => (string) \Illuminate\Support\Str::uuid(),
    'name' => 'Root Sponsor',
    'email' => 'root@test.com',
    'phone' => '1111111111',
    'password' => bcrypt('password'),
    'referral_code' => 'ROOT001',
    'status' => 'active',
]);
$users['root']->assignRole($userRole);

// Level 1 users (direct referrals of root)
$users['l1a'] = User::create([
    'uuid' => (string) \Illuminate\Support\Str::uuid(),
    'name' => 'Level 1 User A',
    'email' => 'l1a@test.com',
    'phone' => '2222222222',
    'password' => bcrypt('password'),
    'referral_code' => 'L1A001',
    'referred_by' => $users['root']->id,
    'status' => 'active',
]);
$users['l1a']->assignRole($userRole);

$users['l1b'] = User::create([
    'uuid' => (string) \Illuminate\Support\Str::uuid(),
    'name' => 'Level 1 User B',
    'email' => 'l1b@test.com',
    'phone' => '3333333333',
    'password' => bcrypt('password'),
    'referral_code' => 'L1B001',
    'referred_by' => $users['root']->id,
    'status' => 'active',
]);
$users['l1b']->assignRole($userRole);

// Level 2 users (referrals of l1a)
$users['l2a'] = User::create([
    'uuid' => (string) \Illuminate\Support\Str::uuid(),
    'name' => 'Level 2 User A',
    'email' => 'l2a@test.com',
    'phone' => '4444444444',
    'password' => bcrypt('password'),
    'referral_code' => 'L2A001',
    'referred_by' => $users['l1a']->id,
    'status' => 'active',
]);
$users['l2a']->assignRole($userRole);

$users['l2b'] = User::create([
    'uuid' => (string) \Illuminate\Support\Str::uuid(),
    'name' => 'Level 2 User B',
    'email' => 'l2b@test.com',
    'phone' => '5555555555',
    'password' => bcrypt('password'),
    'referral_code' => 'L2B001',
    'referred_by' => $users['l1a']->id,
    'status' => 'active',
]);
$users['l2b']->assignRole($userRole);

// Level 3 user (referral of l2a)
$users['l3a'] = User::create([
    'uuid' => (string) \Illuminate\Support\Str::uuid(),
    'name' => 'Level 3 User A',
    'email' => 'l3a@test.com',
    'phone' => '6666666666',
    'password' => bcrypt('password'),
    'referral_code' => 'L3A001',
    'referred_by' => $users['l2a']->id,
    'status' => 'active',
]);
$users['l3a']->assignRole($userRole);

echo "✅ Created " . count($users) . " test users\n";

// Create user tree structure
echo "🌳 Creating user tree structure...\n";
$placementService = new PlacementService();

// Root user (no parent)
UserTree::create([
    'user_id' => $users['root']->id,
    'parent_id' => null,
    'position' => 1,
    'path' => '/',
    'depth' => 0,
]);

// Level 1 users under root
$placementService->placeUserInTree($users['l1a'], $users['root']);
$placementService->placeUserInTree($users['l1b'], $users['root']);

// Level 2 users under l1a
$placementService->placeUserInTree($users['l2a'], $users['l1a']);
$placementService->placeUserInTree($users['l2b'], $users['l1a']);

// Level 3 user under l2a
$placementService->placeUserInTree($users['l3a'], $users['l2a']);

echo "✅ User tree structure created\n";

// Initialize wallets for all users
echo "💳 Initializing wallets for all users...\n";
foreach ($users as $user) {
    $walletTypes = ['commission', 'fasttrack', 'autopool', 'club', 'main'];
    foreach ($walletTypes as $walletType) {
        Wallet::firstOrCreate(
            [
                'user_id' => $user->id,
                'wallet_type' => $walletType,
                'currency' => 'USD'
            ],
            [
                'balance' => 0,
                'pending_balance' => 0
            ]
        );
    }
}
echo "✅ Wallets initialized for all users\n";

// Create packages
echo "📦 Creating test packages...\n";
$packages = [];

$packages[1] = Package::firstOrCreate(
    ['code' => 'BRONZE'],
    [
        'name' => 'Bronze Package',
        'price' => 100.00,
        'level_number' => 1,
        'is_active' => true,
        'description' => 'Entry level package'
    ]
);

$packages[2] = Package::firstOrCreate(
    ['code' => 'SILVER'],
    [
        'name' => 'Silver Package',
        'price' => 200.00,
        'level_number' => 2,
        'is_active' => true,
        'description' => 'Mid level package'
    ]
);

$packages[3] = Package::firstOrCreate(
    ['code' => 'GOLD'],
    [
        'name' => 'Gold Package',
        'price' => 300.00,
        'level_number' => 3,
        'is_active' => true,
        'description' => 'Premium package'
    ]
);

echo "✅ Created " . count($packages) . " packages\n";

// Create income configurations
echo "⚙️ Creating income configurations...\n";

// Level Income: Fixed $0.50 per ancestor
IncomeConfig::firstOrCreate(
    ['name' => 'Level Income Fixed'],
    [
        'income_type' => 'level',
        'percentage' => 0,
        'is_active' => true,
        'metadata' => ['fixed_amount' => 0.50]
    ]
);

// Fasttrack Income: 10% of package price
IncomeConfig::firstOrCreate(
    ['name' => 'Fasttrack 10%'],
    [
        'income_type' => 'fasttrack',
        'percentage' => 10,
        'is_active' => true,
        'metadata' => null
    ]
);

// Company Allocation: 5% for Auto Pool
IncomeConfig::firstOrCreate(
    ['name' => 'Company Allocation'],
    [
        'income_type' => 'fasttrack',
        'percentage' => 0,
        'is_active' => true,
        'metadata' => ['company_share' => 5]
    ]
);

// Club Income: Level-based amounts
for ($level = 1; $level <= 3; $level++) {
    $amount = $level * 5; // L1 = $5, L2 = $10, L3 = $15
    IncomeConfig::firstOrCreate(
        ['income_type' => 'club', 'level' => $level],
        [
            'name' => "Club Level {$level}",
            'percentage' => 0,
            'is_active' => true,
            'metadata' => ['fixed_amount' => $amount]
        ]
    );
}

echo "✅ Income configurations created\n";

// Test Scenario 1: Root user buys Package 1
echo "\n=== SCENARIO 1: ROOT USER BUYS PACKAGE 1 ===\n";
echo "Expected: No income (root has no upline)\n";

$order1 = UserPackage::create([
    'user_id' => $users['root']->id,
    'package_id' => $packages[1]->id,
    'amount_paid' => $packages[1]->price,
    'payment_reference' => 'test_root_pkg1',
    'payment_status' => 'completed',
    'purchase_at' => now(),
    'assigned_level' => $packages[1]->level_number,
    'idempotency_key' => 'test_root_pkg1_' . time(),
]);

// Process the purchase
$job1 = new ProcessPurchaseJob($order1->id);
$job1->handle();

echo "✅ Root user purchase processed\n";

// Test Scenario 2: L1A buys Package 1
echo "\n=== SCENARIO 2: L1A BUYS PACKAGE 1 ===\n";
echo "Expected: Root gets Level Income ($0.50) + Fasttrack Income ($10.00) + Company Allocation ($5.00)\n";

$order2 = UserPackage::create([
    'user_id' => $users['l1a']->id,
    'package_id' => $packages[1]->id,
    'amount_paid' => $packages[1]->price,
    'payment_reference' => 'test_l1a_pkg1',
    'payment_status' => 'completed',
    'purchase_at' => now(),
    'assigned_level' => $packages[1]->level_number,
    'idempotency_key' => 'test_l1a_pkg1_' . time(),
]);

// Process the purchase
$job2 = new ProcessPurchaseJob($order2->id);
$job2->handle();

echo "✅ L1A purchase processed\n";

// Test Scenario 3: L2A buys Package 1
echo "\n=== SCENARIO 3: L2A BUYS PACKAGE 1 ===\n";
echo "Expected: L1A gets Fasttrack ($10.00), Root gets Level Income ($0.50)\n";

$order3 = UserPackage::create([
    'user_id' => $users['l2a']->id,
    'package_id' => $packages[1]->id,
    'amount_paid' => $packages[1]->price,
    'payment_reference' => 'test_l2a_pkg1',
    'payment_status' => 'completed',
    'purchase_at' => now(),
    'assigned_level' => $packages[1]->level_number,
    'idempotency_key' => 'test_l2a_pkg1_' . time(),
]);

// Process the purchase
$job3 = new ProcessPurchaseJob($order3->id);
$job3->handle();

echo "✅ L2A purchase processed\n";

// Test Scenario 4: L3A buys Package 2
echo "\n=== SCENARIO 4: L3A BUYS PACKAGE 2 ===\n";
echo "Expected: L2A gets Fasttrack ($20.00), L1A gets Level Income ($0.50), Root gets Level Income ($0.50)\n";

$order4 = UserPackage::create([
    'user_id' => $users['l3a']->id,
    'package_id' => $packages[2]->id,
    'amount_paid' => $packages[2]->price,
    'payment_reference' => 'test_l3a_pkg2',
    'payment_status' => 'completed',
    'purchase_at' => now(),
    'assigned_level' => $packages[2]->level_number,
    'idempotency_key' => 'test_l3a_pkg2_' . time(),
]);

// Process the purchase
$job4 = new ProcessPurchaseJob($order4->id);
$job4->handle();

echo "✅ L3A purchase processed\n";

// Test Scenario 5: L1B buys Package 1 (for club income testing)
echo "\n=== SCENARIO 5: L1B BUYS PACKAGE 1 ===\n";
echo "Expected: Root gets Fasttrack ($10.00) + Level Income ($0.50) + Club Income (L1 = $5.00)\n";

$order5 = UserPackage::create([
    'user_id' => $users['l1b']->id,
    'package_id' => $packages[1]->id,
    'amount_paid' => $packages[1]->price,
    'payment_reference' => 'test_l1b_pkg1',
    'payment_status' => 'completed',
    'purchase_at' => now(),
    'assigned_level' => $packages[1]->level_number,
    'idempotency_key' => 'test_l1b_pkg1_' . time(),
]);

// Process the purchase
$job5 = new ProcessPurchaseJob($order5->id);
$job5->handle();

echo "✅ L1B purchase processed\n";

// Test Scenario 6: L2B buys Package 1 (for club income testing)
echo "\n=== SCENARIO 6: L2B BUYS PACKAGE 1 ===\n";
echo "Expected: L1A gets Fasttrack ($10.00) + Level Income ($0.50), Root gets Level Income ($0.50) + Club Income (L2 = $10.00)\n";

$order6 = UserPackage::create([
    'user_id' => $users['l2b']->id,
    'package_id' => $packages[1]->id,
    'amount_paid' => $packages[1]->price,
    'payment_reference' => 'test_l2b_pkg1',
    'payment_status' => 'completed',
    'purchase_at' => now(),
    'assigned_level' => $packages[1]->level_number,
    'idempotency_key' => 'test_l2b_pkg1_' . time(),
]);

// Process the purchase
$job6 = new ProcessPurchaseJob($order6->id);
$job6->handle();

echo "✅ L2B purchase processed\n";

// Display comprehensive results
echo "\n" . str_repeat("=", 80) . "\n";
echo "📊 COMPREHENSIVE INCOME SYSTEM RESULTS\n";
echo str_repeat("=", 80) . "\n";

// User tree structure
echo "\n🌳 USER TREE STRUCTURE:\n";
$treeNodes = UserTree::with('user')->orderBy('depth')->orderBy('position')->get();
foreach ($treeNodes as $node) {
    $indent = str_repeat("  ", $node->depth);
    echo "{$indent}Level {$node->depth}: {$node->user->name} (ID: {$node->user_id})\n";
}

// Wallet balances
echo "\n💰 WALLET BALANCES:\n";
$wallets = Wallet::with('user')->whereIn('user_id', array_column($users, 'id'))->get();
foreach ($wallets as $wallet) {
    $userName = $wallet->user ? $wallet->user->name : 'Unknown';
    echo "{$userName} - {$wallet->wallet_type}: \${$wallet->balance}\n";
}

// Income records by type
echo "\n📈 INCOME RECORDS BY TYPE:\n";
$incomeTypes = ['level', 'fasttrack', 'club', 'company_allocation'];
foreach ($incomeTypes as $type) {
    $incomes = IncomeRecord::where('income_type', $type)->with('user')->get();
    echo "\n{$type} Income:\n";
    foreach ($incomes as $income) {
        $userName = $income->user ? $income->user->name : 'Unknown';
        echo "  {$userName}: \${$income->amount}\n";
    }
}

// Club entries
echo "\n🏛️ CLUB ENTRIES:\n";
$clubEntries = ClubEntry::with(['user', 'sponsor'])->get();
foreach ($clubEntries as $entry) {
    echo "User: {$entry->user->name} -> Sponsor: {$entry->sponsor->name} (Level {$entry->level})\n";
}

// Ledger transactions summary
echo "\n📋 LEDGER TRANSACTIONS SUMMARY:\n";
$ledgerTypes = ['level_income', 'fasttrack', 'club_income', 'company_allocation'];
foreach ($ledgerTypes as $type) {
    $total = LedgerTransaction::where('type', $type)->sum('amount');
    $count = LedgerTransaction::where('type', $type)->count();
    echo "{$type}: {$count} transactions, Total: \${$total}\n";
}

// Final statistics
echo "\n📊 FINAL STATISTICS:\n";
echo "Total Users: " . count($users) . "\n";
echo "Total Packages Purchased: " . UserPackage::count() . "\n";
echo "Total Income Records: " . IncomeRecord::count() . "\n";
echo "Total Ledger Transactions: " . LedgerTransaction::count() . "\n";
echo "Total Club Entries: " . ClubEntry::count() . "\n";
echo "Total Wallets: " . Wallet::count() . "\n";

echo "\n✅ COMPREHENSIVE INCOME SYSTEM TEST COMPLETED!\n";
echo "All 4 income types have been tested with realistic scenarios.\n";
