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
        Schema::table('user_packages', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->unique()->after('payment_meta');
            $table->boolean('processing')->default(false)->after('idempotency_key');
            $table->timestamp('processed_at')->nullable()->after('processing');
            $table->softDeletes()->after('updated_at');

            // Add index for idempotency_key for performance
            $table->index('idempotency_key');
            $table->index('processing');
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_packages', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->dropIndex(['processing']);
            $table->dropIndex(['processed_at']);
            $table->dropColumn(['idempotency_key', 'processing', 'processed_at', 'deleted_at']);
        });
    }
};
