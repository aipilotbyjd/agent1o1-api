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
        Schema::create('trigger_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('trigger_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('mechanism')->default('push');

            // What an incoming provider event is matched on (a Slack team id).
            $table->string('routing_key');
            $table->string('status')->default('pending');
            $table->string('external_id')->nullable();
            $table->json('cursor')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('last_ok_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['provider', 'routing_key', 'status']);
            $table->index('expires_at');
        });

        Schema::table('triggers', function (Blueprint $table) {
            // When a polling trigger is next due: an indexed claim instead of
            // reading every polling trigger each minute. Null means due now.
            $table->timestamp('next_poll_at')->nullable();
            $table->index(['type', 'is_active', 'next_poll_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->dropIndex(['type', 'is_active', 'next_poll_at']);
            $table->dropColumn('next_poll_at');
        });

        Schema::dropIfExists('trigger_subscriptions');
    }
};
