<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail: rows are only ever inserted, never updated or deleted,
     * so there is no updated_at column.
     */
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('candidacy_id');
            $table->string('evaluator_id')->nullable();
            $table->string('action');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at');

            $table->foreign('candidacy_id')->references('id')->on('candidacies')->cascadeOnDelete();
            $table->foreign('evaluator_id')->references('id')->on('evaluators')->nullOnDelete();
            $table->index(['candidacy_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
