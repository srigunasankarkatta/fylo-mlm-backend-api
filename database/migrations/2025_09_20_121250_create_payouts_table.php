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
        Schema::create('payouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('wallet_id'); // wallet funds drawn from
            $table->decimal('amount', 28, 8);
            $table->decimal('fee', 28, 8)->default(0);
            $table->json('payout_method')->nullable(); // masked bank details
            $table->enum('status', ['requested', 'processing', 'completed', 'failed', 'rejected'])->default('requested');
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->onDelete('set null');

            // Indexes for performance
            $table->index('user_id');
            $table->index('wallet_id');
            $table->index('status');
            $table->index('processed_by');
            $table->index('processed_at');
            $table->index('ledger_transaction_id');
            $table->index(['user_id', 'status']);
            $table->index(['status', 'processed_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
