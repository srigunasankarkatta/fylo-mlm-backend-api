<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('income_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id'); // beneficiary
            $table->unsignedBigInteger('origin_user_id')->nullable(); // purchaser/trigger
            $table->unsignedBigInteger('user_package_id')->nullable();
            $table->unsignedBigInteger('income_config_id')->nullable();
            $table->enum('income_type', ['level', 'fasttrack', 'club', 'autopool', 'other']);
            $table->decimal('amount', 28, 8);
            $table->string('currency', 10)->default('USD');
            $table->enum('status', ['pending', 'paid', 'reversed'])->default('pending');
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('origin_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_package_id')->references('id')->on('user_packages')->onDelete('set null');
            $table->foreign('income_config_id')->references('id')->on('income_configs')->onDelete('set null');
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->onDelete('set null');

            // Indexes for performance
            $table->index('user_id');
            $table->index('origin_user_id');
            $table->index('user_package_id');
            $table->index('income_config_id');
            $table->index('income_type');
            $table->index('status');
            $table->index('currency');
            $table->index('ledger_transaction_id');
            $table->index('created_at');
            $table->index(['user_id', 'income_type']);
            $table->index(['user_id', 'status']);
            $table->index(['origin_user_id', 'income_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_records');
    }
};
