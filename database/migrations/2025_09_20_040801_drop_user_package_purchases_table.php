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
        Schema::dropIfExists('user_package_purchases');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('user_package_purchases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('package_id');
            $table->decimal('amount_paid', 28, 8);
            $table->string('payment_reference')->nullable();
            $table->enum('payment_status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('purchase_at')->nullable();
            $table->tinyInteger('assigned_level')->nullable();
            $table->json('payment_meta')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('package_id')->references('id')->on('packages')->onDelete('cascade');

            $table->index('user_id');
            $table->index('package_id');
            $table->index('payment_status');
            $table->index('purchase_at');
        });
    }
};
