<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes backing the consolidated evaluator listing read-query: the join
     * column plus every column the whitelist allows sorting/filtering on.
     */
    public function up(): void
    {
        Schema::table('candidacies', function (Blueprint $table) {
            $table->index(['evaluator_id', 'assigned_at']);
            $table->index('years_of_experience');
            $table->index('full_name');
            $table->index('assigned_at');
        });

        Schema::table('evaluators', function (Blueprint $table) {
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('candidacies', function (Blueprint $table) {
            $table->dropIndex(['evaluator_id', 'assigned_at']);
            $table->dropIndex(['years_of_experience']);
            $table->dropIndex(['full_name']);
            $table->dropIndex(['assigned_at']);
        });

        Schema::table('evaluators', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};
