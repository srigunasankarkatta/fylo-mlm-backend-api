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
        Schema::create('club_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id'); // The user being placed in club
            $table->unsignedBigInteger('sponsor_id'); // The sponsor whose club tree this user joins
            $table->tinyInteger('level')->default(1); // Level in the club tree (1-10)
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('sponsor_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index('user_id');
            $table->index('sponsor_id');
            $table->index('level');
            $table->index('status');
            $table->index(['sponsor_id', 'level']);
            $table->index(['user_id', 'sponsor_id']);

            // Unique constraint: a user can only be in one sponsor's club tree
            $table->unique(['user_id', 'sponsor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_entries');
    }
};
