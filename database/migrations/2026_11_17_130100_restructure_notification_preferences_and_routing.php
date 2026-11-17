<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Splits "who hears about an event" from "which chat or webhook channels
 * hear about it".
 *
 * Chat and webhook routing used to live on each member's preference
 * (`notification_preferences.channel_ids`), so a channel picked by three
 * admins got every message three times. It is now the workspace's
 * (`notification_routes`) and is evaluated once per event. Existing picks
 * are carried over, one route per channel and event.
 *
 * Preferences gain category-wide rows (`event_key` = `category:<name>`, no
 * schema change needed), a daily-digest option for email, and an
 * account-level table for events that aren't about a workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('email_digest')->default(false)->after('email');
        });

        Schema::create('account_notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(false);
            $table->boolean('email_digest')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });

        Schema::table('notification_channels', function (Blueprint $table) {
            $table->text('signing_secret')->nullable()->after('config');
            $table->unsignedInteger('consecutive_failures')->default(0)->after('is_active');
            $table->timestamp('last_delivered_at')->nullable()->after('consecutive_failures');
            $table->timestamp('last_failed_at')->nullable()->after('last_delivered_at');
            $table->string('last_error', 500)->nullable()->after('last_failed_at');
            $table->timestamp('disabled_at')->nullable()->after('last_error');
        });

        Schema::create('notification_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('notification_channel_id')->constrained()->cascadeOnDelete();
            $table->string('event_key')->nullable();
            $table->string('category', 40)->nullable();
            $table->string('min_severity', 20)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'event_key']);
            $table->index(['workspace_id', 'category']);
        });

        $this->carryOverChannelPicks();

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('channel_ids');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->json('channel_ids')->nullable();
        });

        Schema::dropIfExists('notification_routes');
        Schema::dropIfExists('account_notification_preferences');

        Schema::table('notification_channels', function (Blueprint $table) {
            $table->dropColumn(['signing_secret', 'consecutive_failures', 'last_delivered_at', 'last_failed_at', 'last_error', 'disabled_at']);
        });

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('email_digest');
        });
    }

    private function carryOverChannelPicks(): void
    {
        $created = [];

        DB::table('notification_preferences')
            ->whereNotNull('channel_ids')
            ->orderBy('id')
            ->chunk(500, function ($preferences) use (&$created): void {
                foreach ($preferences as $preference) {
                    $channelIds = json_decode((string) $preference->channel_ids, true);

                    if (! is_array($channelIds) || $channelIds === []) {
                        continue;
                    }

                    $validChannelIds = DB::table('notification_channels')
                        ->where('workspace_id', $preference->workspace_id)
                        ->whereIn('id', array_map('strval', $channelIds))
                        ->pluck('id');

                    foreach ($validChannelIds as $channelId) {
                        $routeKey = "{$channelId}|{$preference->event_key}";

                        if (isset($created[$routeKey])) {
                            continue;
                        }

                        $created[$routeKey] = true;

                        DB::table('notification_routes')->insert([
                            'id' => (string) Str::uuid(),
                            'workspace_id' => $preference->workspace_id,
                            'notification_channel_id' => $channelId,
                            'event_key' => $preference->event_key,
                            'category' => null,
                            'min_severity' => null,
                            'created_by' => $preference->user_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }
};
