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
        Schema::create('node_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('runs')->cascadeOnDelete();
            $table->string('key');
            $table->string('type');
            $table->string('status')->default('pending');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->json('usage')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->unsignedInteger('max_attempts')->default(1);
            $table->unsignedInteger('retry_delay_seconds')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('callback_token')->nullable()->unique();
            $table->timestamp('callback_expires_at')->nullable();
            $table->json('state')->nullable();
            $table->boolean('pending_advance')->default(false);
            $table->timestamps();

            $table->unique(['run_id', 'key']);
            $table->index(['run_id', 'status']);
            $table->index('callback_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('node_runs');
    }
};
