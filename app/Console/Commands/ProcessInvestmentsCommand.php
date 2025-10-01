<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessInvestmentJob;
use App\Models\UserInvestment;
use Illuminate\Support\Facades\Log;

class ProcessInvestmentsCommand extends Command
{
    protected $signature = 'investments:process {--type=all : Type of processing (all, daily, maturity)}';
    protected $description = 'Process all active investments for daily interest and maturity';

    public function handle()
    {
        $type = $this->option('type');

        $this->info("🚀 Processing investments...");
        $this->info("Type: {$type}");

        try {
            switch ($type) {
                case 'daily':
                    $this->processDailyInvestments();
                    break;
                case 'maturity':
                    $this->processMaturityInvestments();
                    break;
                case 'all':
                default:
                    $this->processDailyInvestments();
                    $this->processMaturityInvestments();
                    break;
            }

            $this->info("✅ Investment processing completed successfully!");
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Investment processing failed: " . $e->getMessage());
            Log::error("ProcessInvestmentsCommand failed: " . $e->getMessage());
            return 1;
        }
    }

    protected function processDailyInvestments()
    {
        $this->info("📈 Processing daily interest accrual...");

        $investments = UserInvestment::forDailyAccrual()
            ->with(['user', 'investmentPlan'])
            ->get();

        $this->info("Found {$investments->count()} investments for daily accrual");

        $processed = 0;
        foreach ($investments as $investment) {
            try {
                dispatch(new ProcessInvestmentJob($investment->id));
                $processed++;

                if ($processed % 10 === 0) {
                    $this->info("Dispatched {$processed} jobs...");
                }
            } catch (\Exception $e) {
                $this->error("Failed to dispatch job for investment {$investment->id}: " . $e->getMessage());
            }
        }

        $this->info("✅ Dispatched {$processed} daily interest jobs");
    }

    protected function processMaturityInvestments()
    {
        $this->info("🎯 Processing mature investments...");

        $investments = UserInvestment::dueForMaturity()
            ->with(['user', 'investmentPlan'])
            ->get();

        $this->info("Found {$investments->count()} investments due for maturity");

        $processed = 0;
        foreach ($investments as $investment) {
            try {
                dispatch(new ProcessInvestmentJob($investment->id));
                $processed++;

                if ($processed % 10 === 0) {
                    $this->info("Dispatched {$processed} jobs...");
                }
            } catch (\Exception $e) {
                $this->error("Failed to dispatch job for investment {$investment->id}: " . $e->getMessage());
            }
        }

        $this->info("✅ Dispatched {$processed} maturity jobs");
    }
}
