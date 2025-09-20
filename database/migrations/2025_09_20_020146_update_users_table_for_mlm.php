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
        Schema::table('users', function (Blueprint $table) {
            // Add UUID column
            $table->uuid('uuid')->unique()->after('id');

            // Modify existing columns
            $table->string('name', 150)->nullable()->change();
            $table->string('email', 150)->nullable()->change();
            $table->string('password')->nullable()->change();

            // Add phone column
            $table->string('phone', 30)->unique()->nullable()->after('email');

            // Add phone verification
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');

            // Add referral system columns
            $table->string('referral_code', 20)->unique()->after('phone_verified_at');
            $table->unsignedBigInteger('referred_by')->nullable()->after('referral_code');

            // Add MLM tree structure columns
            $table->unsignedBigInteger('parent_id')->nullable()->after('referred_by');
            $table->tinyInteger('position')->nullable()->after('parent_id');

            // Add role hint
            $table->string('role_hint', 50)->nullable()->after('position');

            // Add status and metadata
            $table->enum('status', ['active', 'inactive', 'suspended', 'banned', 'deleted'])
                ->default('active')->after('role_hint');
            $table->json('metadata')->nullable()->after('status');

            // Add audit columns
            $table->ipAddress('last_login_ip')->nullable()->after('metadata');
            $table->timestamp('last_login_at')->nullable()->after('last_login_ip');

            // Add soft deletes
            $table->softDeletes();

            // Add indexes
            $table->index('referred_by');
            $table->index('parent_id');
            $table->unique(['parent_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove indexes first
            $table->dropUnique(['parent_id', 'position']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['referred_by']);

            // Remove columns
            $table->dropColumn([
                'uuid',
                'phone',
                'phone_verified_at',
                'referral_code',
                'referred_by',
                'parent_id',
                'position',
                'role_hint',
                'status',
                'metadata',
                'last_login_ip',
                'last_login_at',
                'deleted_at'
            ]);

            // Revert column changes
            $table->string('name')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
