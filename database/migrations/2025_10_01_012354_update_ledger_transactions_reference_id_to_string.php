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
        Schema::table('ledger_transactions', function (Blueprint $table) {
            // Change reference_id from unsignedBigInteger to string to support club income reference IDs
            $table->string('reference_id', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            // Revert back to unsignedBigInteger
            $table->unsignedBigInteger('reference_id')->nullable()->change();
        });
    }
};
