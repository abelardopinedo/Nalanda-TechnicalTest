<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('requested_by_email');
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('error_message')->nullable();
            $table->json('filters_snapshot')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
