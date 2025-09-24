<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvestmentPlansTable extends Migration
{
    public function up()
    {
        Schema::create('investment_plans', function (Blueprint $table) {
            $table->bigIncrements('id');

            // basic info
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();

            // limits & economics
            $table->decimal('min_amount', 28, 8)->default(0);
            $table->decimal('max_amount', 28, 8)->nullable(); // null => no upper limit
            $table->decimal('daily_profit_percent', 10, 6)->default(0); // e.g., 1.5 => 1.5%
            $table->unsignedInteger('duration_days')->default(30); // duration in days
            $table->decimal('referral_percent', 10, 6)->default(0); // percent for referrer

            // admin controls & meta
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->json('metadata')->nullable();

            // auditing
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'min_amount', 'max_amount']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('investment_plans');
    }
}
