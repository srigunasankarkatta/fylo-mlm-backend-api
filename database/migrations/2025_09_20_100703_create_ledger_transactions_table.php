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
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_from')->nullable();
            $table->unsignedBigInteger('user_to')->nullable();
            $table->unsignedBigInteger('wallet_from_id')->nullable();
            $table->unsignedBigInteger('wallet_to_id')->nullable();
            $table->enum('type', [
                'level_income',
                'fasttrack',
                'club_income',
                'autopool_income',
                'purchase',
                'refund',
                'payout',
                'fee',
                'company_allocation',
                'adjustment'
            ]);
            $table->decimal('amount', 28, 8);
            $table->string('currency', 10)->default('USD');
            $table->unsignedBigInteger('reference_id')->nullable(); // e.g., user_packages.id
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Foreign key constraints
            $table->foreign('user_from')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('wallet_from_id')->references('id')->on('wallets')->onDelete('set null');
            $table->foreign('wallet_to_id')->references('id')->on('wallets')->onDelete('set null');

            // Indexes for performance
            $table->index('user_from');
            $table->index('user_to');
            $table->index('wallet_from_id');
            $table->index('wallet_to_id');
            $table->index('type');
            $table->index('currency');
            $table->index('reference_id');
            $table->index('created_at');
            $table->index(['user_from', 'type']);
            $table->index(['user_to', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
