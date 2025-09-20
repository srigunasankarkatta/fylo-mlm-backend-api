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
        Schema::create('wallets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable(); // null => company wallet
            $table->enum('wallet_type', ['commission', 'fasttrack', 'autopool', 'club', 'main', 'company_total']);
            $table->string('currency', 10)->default('USD');
            $table->decimal('balance', 28, 8)->default(0);
            $table->decimal('pending_balance', 28, 8)->default(0);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint for user_id, wallet_type, and currency combination
            $table->unique(['user_id', 'wallet_type', 'currency']);

            // Indexes for performance
            $table->index('user_id');
            $table->index('wallet_type');
            $table->index('currency');
            $table->index(['user_id', 'wallet_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
