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
        Schema::create('trigger_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trigger_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('status')->default('queued');

            // No FK yet: workflow_runs doesn't exist until the workflow engine ships
            // (docs/WORKFLOWS_PLAN.md). Column is real; constraint follows.
            $table->foreignUuid('run_id')->nullable()->constrained('runs')->nullOnDelete();

            $table->json('payload')->nullable();
            $table->text('payload_snippet')->nullable();
            $table->json('headers')->nullable();
            $table->text('error')->nullable();
            $table->string('delivery_id')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['trigger_id', 'delivery_id']);
            $table->index(['trigger_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trigger_events');
    }
};
