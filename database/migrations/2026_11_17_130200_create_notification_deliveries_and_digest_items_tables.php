<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `notification_deliveries` records every email and chat/webhook message a
 * notification produced and how it went, so "did Slack get it?" has an
 * answer and failing destinations show up in the admin's health page.
 *
 * `notification_digest_items` holds emails a member asked to receive as one
 * daily digest until `notifications:send-digests` sends them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable()->index();
            $table->uuid('workspace_id')->nullable()->index();
            $table->foreignUuid('notification_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key')->nullable();
            $table->string('channel', 20);
            $table->string('target')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['notification_channel_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['event_key', 'created_at']);
        });

        Schema::create('notification_digest_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('workspace_id')->nullable();
            $table->string('event_key');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action_url', 2048)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_digest_items');
        Schema::dropIfExists('notification_deliveries');
    }
};
