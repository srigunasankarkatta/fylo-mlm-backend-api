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
        Schema::create('club_matrix', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sponsor_id');
            $table->unsignedBigInteger('member_id');
            $table->integer('depth')->default(1);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('sponsor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index('sponsor_id');
            $table->index('member_id');
            $table->index(['sponsor_id', 'member_id']);
            $table->index('depth');
            $table->index(['sponsor_id', 'depth']);
            $table->index(['member_id', 'depth']);

            // Ensure no duplicate sponsor-member relationships
            $table->unique(['sponsor_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_matrix');
    }
};
