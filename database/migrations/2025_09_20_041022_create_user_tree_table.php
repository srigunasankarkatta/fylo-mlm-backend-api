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
        Schema::create('user_tree', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique(); // node owner
            $table->unsignedBigInteger('parent_id')->nullable(); // immediate upline
            $table->tinyInteger('position')->nullable(); // 1..4 (unique per parent)
            $table->string('path')->nullable(); // e.g., "/1/12/45/"
            $table->integer('depth')->default(0);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('user_tree')->onDelete('cascade');

            // Indexes for performance
            $table->index('parent_id');
            $table->index('position');
            $table->index('path');
            $table->index('depth');

            // Unique constraint for position per parent
            $table->unique(['parent_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tree');
    }
};
