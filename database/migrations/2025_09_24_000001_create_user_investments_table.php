<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserInvestmentsTable extends Migration
{
    public function up()
    {
        Schema::create('user_investments', function (Blueprint $table) {
            $table->bigIncrements('id');

            // user & plan reference
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('investment_plan_id');

            // investment details
            $table->decimal('amount', 28, 8);
            $table->decimal('daily_profit_percent', 10, 6); // snapshot of plan's percent
            $table->unsignedInteger('duration_days');

            // lifecycle
            $table->timestamp('invested_at')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamp('matured_at')->nullable();

            // tracking earnings
            $table->decimal('accrued_interest', 28, 8)->default(0);
            $table->decimal('total_payout', 28, 8)->default(0);

            // status
            $table->enum('status', [
                'pending',   // requested, not yet funded
                'active',    // currently accruing interest
                'completed', // matured + payout done
                'cancelled', // cancelled before maturity
                'withdrawn'  // user withdrew early
            ])->default('pending');

            // referral linkage (optional)
            $table->unsignedBigInteger('referrer_id')->nullable();
            $table->decimal('referral_commission', 28, 8)->default(0);

            // metadata
            $table->json('metadata')->nullable();

            // auditing
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // indexes
            $table->index(['user_id', 'investment_plan_id']);
            $table->index(['status']);
            $table->index(['start_at', 'end_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_investments');
    }
}
