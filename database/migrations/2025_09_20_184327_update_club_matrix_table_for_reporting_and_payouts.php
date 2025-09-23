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
        Schema::table('club_matrix', function (Blueprint $table) {
            // Add soft deletes if not exists
            if (!Schema::hasColumn('club_matrix', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }

            // Add indexes for performance if they don't exist
            $indexes = [
                ['sponsor_id', 'depth'],
                ['member_id', 'depth'],
                ['depth']
            ];

            foreach ($indexes as $index) {
                $indexName = 'club_matrix_' . implode('_', $index) . '_index';
                if (!Schema::hasIndex('club_matrix', $indexName)) {
                    $table->index($index, $indexName);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_matrix', function (Blueprint $table) {
            $table->dropIndex(['sponsor_id', 'depth']);
            $table->dropIndex(['member_id', 'depth']);
            $table->dropIndex('depth');
            $table->dropSoftDeletes();
        });
    }
};
