<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triggers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('credential_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_paused')->default(false);
            $table->uuid('webhook_uuid')->nullable()->unique();
            $table->string('webhook_provider')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('webhook_registered_url')->nullable();
            $table->string('webhook_status')->nullable();
            $table->unsignedInteger('polling_interval_seconds')->nullable();
            $table->timestamp('polling_last_check_at')->nullable();
            $table->timestamp('polling_next_check_at')->nullable();
            $table->json('polling_last_seen_ids')->nullable();
            $table->string('schedule_expression')->nullable();
            $table->timestamp('schedule_next_run_at')->nullable();
            $table->string('schedule_timezone')->default('UTC');
            $table->unsignedInteger('max_concurrency')->default(1);
            $table->unsignedInteger('rate_limit_count')->nullable();
            $table->unsignedInteger('rate_limit_window')->nullable();
            $table->unsignedBigInteger('total_events')->default(0);
            $table->unsignedBigInteger('total_executions')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
            $table->index(['type', 'polling_next_check_at']);
            $table->index(['type', 'schedule_next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triggers');
    }
};
