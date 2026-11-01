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
        Schema::create('provider_watches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('credential_id')->nullable()->constrained('connector_credentials')->cascadeOnDelete();
            $table->string('provider');

            // What is watched, so triggers on the same thing share one watch
            // (one Gmail mailbox, one Outlook folder, one repository event).
            $table->string('resource_key');
            $table->string('external_id')->nullable();
            $table->text('secret')->nullable();
            $table->json('meta')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();

            // When the renewal command looks at it next; null for a watch that never expires.
            $table->timestamp('renew_at')->nullable();

            // Set once the last trigger leaves; the watch is removed after a grace period.
            $table->timestamp('release_at')->nullable();
            $table->timestamp('last_ok_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'credential_id', 'resource_key']);
            $table->index(['status', 'renew_at']);
            $table->index('release_at');
        });

        Schema::table('trigger_subscriptions', function (Blueprint $table) {
            $table->foreignUuid('watch_id')->nullable()->constrained('provider_watches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trigger_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('watch_id');
        });

        Schema::dropIfExists('provider_watches');
    }
};
