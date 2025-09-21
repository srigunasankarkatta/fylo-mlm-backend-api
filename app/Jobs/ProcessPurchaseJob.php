<?php

namespace App\Jobs;

use App\Models\UserPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessPurchaseJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $userPackageId;

    public function __construct(int $userPackageId)
    {
        $this->userPackageId = $userPackageId;
    }

    public function handle()
    {
        // Load the order
        $order = UserPackage::with('user', 'package')->find($this->userPackageId);
        if (!$order) return;

        // Idempotency: check if there's already processing marker
        if ($order->processed_at) {
            return; // already processed
        }

        DB::transaction(function () use ($order) {
            // Mark processing started
            $order->update(['processing' => true]); // assume boolean 'processing' column

            // 1. Level Income: walk up ancestors and create income_records + ledger + wallet updates
            // 2. Fasttrack: calculate % per income_configs and credit immediate uplines + company allocation
            // 3. AutoPool: create auto_pool_entries and maybe immediate small payouts or enqueue auto_pool job
            // 4. Club: check sponsor club progression
            // Implementation details are in specialized services (IncomeCalculator, WalletService)
            // Example:
            // app(IncomeCalculator::class)->distributeLevelIncome($order);
            // app(IncomeCalculator::class)->distributeFasttrack($order);
            // app(AutoPoolService::class)->enqueueForPool($order);

            // mark processed
            $order->update(['processing' => false, 'processed_at' => now()]);
        });
    }
}
