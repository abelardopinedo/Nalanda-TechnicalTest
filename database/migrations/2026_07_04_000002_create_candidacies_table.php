<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidacies', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('full_name');
            $table->string('email');
            $table->unsignedSmallInteger('years_of_experience');
            $table->text('cv_text');
            $table->string('status');
            $table->string('evaluator_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->foreign('evaluator_id')->references('id')->on('evaluators')->nullOnDelete();
            $table->index('status');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidacies');
    }
};
