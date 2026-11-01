<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per `Agent`, whether/how it periodically reviews its own past
 * `AgentSession`s and proposes improvements. See
 * `Services\Agents\ReflectionAnalyzer` and docs/gumloop/output/raw/core-concepts/reflections.md,
 * the feature this mirrors.
 *
 * Report delivery itself (email/in-app/webhook) rides the existing
 * `NotificationEvent::ReflectionRunCompleted`/`NotificationPreference`
 * system, not a bespoke toggle here: `notify_on_skip` is the one setting
 * that doesn't fit that system, since it decides whether a *skipped* run
 * fires a notification at all, not which channel a completed one uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reflection_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('apply_behavior')->default('review_queue');
            $table->string('schedule_cron')->default('0 22 * * *');
            $table->unsignedSmallInteger('min_chats_threshold')->default(5);
            $table->text('extra_instructions')->nullable();
            $table->boolean('notify_on_skip')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reflection_settings');
    }
};
