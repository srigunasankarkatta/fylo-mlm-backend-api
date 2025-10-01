<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessAutoPoolJob;
use App\Models\AutoPoolEntry;
use App\Models\Package;
use Illuminate\Support\Facades\Log;

class ProcessAutoPoolCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'autopool:process {--amount=100} {--package=1} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process AutoPool income distribution for active entries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $amount = (float) $this->option('amount');
        $packageId = (int) $this->option('package');
        $force = $this->option('force');

        $this->info("🚀 Processing AutoPool distribution...");
        $this->info("Amount: $" . number_format($amount, 2));
        $this->info("Package ID: {$packageId}");

        // Check if there are active AutoPool entries
        $activeEntries = AutoPoolEntry::where('status', 'active')->count();

        if ($activeEntries === 0) {
            $this->warn("⚠️  No active AutoPool entries found.");

            if (!$force) {
                $this->info("💡 Use --force to process anyway (for testing).");
                return 0;
            }
        }

        $this->info("📊 Active AutoPool entries: {$activeEntries}");

        try {
            // Create and dispatch the job
            $job = new ProcessAutoPoolJob($packageId, $amount);
            dispatch($job);

            $this->info("✅ ProcessAutoPoolJob dispatched successfully!");
            $this->info("📝 Check logs for detailed processing information.");

            Log::info("ProcessAutoPoolCommand: Dispatched ProcessAutoPoolJob with amount {$amount} and package {$packageId}");

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to dispatch ProcessAutoPoolJob: " . $e->getMessage());
            Log::error("ProcessAutoPoolCommand failed: " . $e->getMessage());

            return 1;
        }
    }
}
