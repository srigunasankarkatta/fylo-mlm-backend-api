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
        Schema::create('auto_pool_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('package_id');
            $table->tinyInteger('pool_level'); // 1..10
            $table->tinyInteger('pool_sub_level'); // 1..8
            $table->timestamp('placed_at')->nullable();
            $table->enum('status', ['active', 'completed', 'paid_out'])->default('active');
            $table->unsignedBigInteger('allocated_by')->nullable(); // system/admin id
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('package_id')->references('id')->on('packages')->onDelete('cascade');
            $table->foreign('allocated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index('user_id');
            $table->index('package_id');
            $table->index('pool_level');
            $table->index('pool_sub_level');
            $table->index('status');
            $table->index('placed_at');
            $table->index('allocated_by');
            $table->index(['pool_level', 'pool_sub_level']);
            $table->index(['package_id', 'pool_level', 'pool_sub_level']);
            $table->index(['status', 'pool_level', 'pool_sub_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_pool_entries');
    }
};
