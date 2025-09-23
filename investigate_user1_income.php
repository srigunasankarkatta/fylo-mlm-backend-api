<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\UserPackage;
use App\Models\IncomeRecord;
use App\Models\Wallet;
use App\Models\UserTree;

echo "=== INVESTIGATING USER1 INCOME ISSUE ===\n\n";

// Find User1
$user1 = User::where('email', 'user1@mlm.com')->first();
if (!$user1) {
    echo "❌ User1 not found with email user1@mlm.com\n";
    exit;
}

echo "✅ User1 Found:\n";
echo "  ID: {$user1->id}\n";
echo "  Name: {$user1->name}\n";
echo "  Email: {$user1->email}\n";
echo "  Referral Code: {$user1->referral_code}\n";
echo "  Referred By: {$user1->referred_by}\n";
echo "  Status: {$user1->status}\n\n";

// Check User1's referrals
$referrals = User::where('referred_by', $user1->id)->get();
echo "📊 User1's Referrals: {$referrals->count()}\n";
if ($referrals->count() > 0) {
    foreach ($referrals as $referral) {
        echo "  - ID: {$referral->id}, Name: {$referral->name}, Email: {$referral->email}\n";
    }
}
echo "\n";

// Check User1's packages
$user1Packages = UserPackage::where('user_id', $user1->id)->get();
echo "📦 User1's Packages: {$user1Packages->count()}\n";
if ($user1Packages->count() > 0) {
    foreach ($user1Packages as $package) {
        echo "  - Package ID: {$package->package_id}, Status: {$package->payment_status}, Amount: {$package->amount_paid}, Processed: " . ($package->processed_at ? 'Yes' : 'No') . "\n";
    }
} else {
    echo "  ❌ No packages found for User1\n";
}
echo "\n";

// Check User1's income records
$user1Income = IncomeRecord::where('user_id', $user1->id)->get();
echo "💰 User1's Income Records: {$user1Income->count()}\n";
if ($user1Income->count() > 0) {
    $totalIncome = $user1Income->sum('amount');
    echo "  Total Income: {$totalIncome}\n";
    foreach ($user1Income as $record) {
        echo "  - Type: {$record->income_type}, Amount: {$record->amount}, Origin: {$record->origin_user_id}, Status: {$record->status}\n";
    }
} else {
    echo "  ❌ No income records found for User1\n";
}
echo "\n";

// Check User1's wallets
$user1Wallets = Wallet::where('user_id', $user1->id)->get();
echo "💳 User1's Wallets:\n";
foreach ($user1Wallets as $wallet) {
    echo "  - {$wallet->wallet_type}: {$wallet->balance} {$wallet->currency}\n";
}
echo "\n";

// Check User1's tree position
$user1Tree = UserTree::where('user_id', $user1->id)->first();
if ($user1Tree) {
    echo "🌳 User1's Tree Position:\n";
    echo "  - Tree ID: {$user1Tree->id}\n";
    echo "  - Parent ID: {$user1Tree->parent_id}\n";
    echo "  - Position: {$user1Tree->position}\n";
    echo "  - Depth: {$user1Tree->depth}\n";
} else {
    echo "❌ User1 not found in tree\n";
}
echo "\n";

// Check referrals' packages
echo "📦 Referrals' Packages:\n";
foreach ($referrals as $referral) {
    $refPackages = UserPackage::where('user_id', $referral->id)->get();
    echo "  {$referral->name} (ID: {$referral->id}): {$refPackages->count()} packages\n";
    foreach ($refPackages as $pkg) {
        echo "    - Package: {$pkg->package_id}, Status: {$pkg->payment_status}, Amount: {$pkg->amount_paid}, Processed: " . ($pkg->processed_at ? 'Yes' : 'No') . "\n";
    }
}
echo "\n";

// Check if there are any unprocessed packages
$unprocessedPackages = UserPackage::where('payment_status', 'completed')
    ->whereNull('processed_at')
    ->get();
echo "🔍 Unprocessed Packages: {$unprocessedPackages->count()}\n";
if ($unprocessedPackages->count() > 0) {
    foreach ($unprocessedPackages as $package) {
        $user = User::find($package->user_id);
        echo "  - Package ID: {$package->id}, User: {$user->name} (ID: {$package->user_id}), Amount: {$package->amount_paid}\n";
    }
}
