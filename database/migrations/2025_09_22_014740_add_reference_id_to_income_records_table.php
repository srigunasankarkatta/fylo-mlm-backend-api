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
        Schema::table('income_records', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_id')->nullable()->after('ledger_transaction_id');
            $table->index('reference_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('income_records', function (Blueprint $table) {
            $table->dropIndex(['reference_id']);
            $table->dropColumn('reference_id');
        });
    }
};
