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
        Schema::create('income_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->enum('income_type', ['level', 'fasttrack', 'club', 'autopool', 'other']);
            $table->unsignedTinyInteger('package_id')->nullable(); // null => global
            $table->tinyInteger('level')->nullable(); // 1..10 (for autopool/club)
            $table->tinyInteger('sub_level')->nullable(); // 1..8 (fixed)
            $table->decimal('percentage', 18, 10); // store as fraction (e.g., 0.005 => 0.5%)
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->json('metadata')->nullable(); // caps, min, rules
            $table->unsignedBigInteger('created_by')->nullable(); // admin user
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('package_id')->references('id')->on('packages')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index('income_type');
            $table->index('package_id');
            $table->index('level');
            $table->index('sub_level');
            $table->index('is_active');
            $table->index('version');
            $table->index('effective_from');
            $table->index('effective_to');
            $table->index(['income_type', 'package_id']);
            $table->index(['income_type', 'level']);
            $table->index(['package_id', 'level', 'sub_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_configs');
    }
};
