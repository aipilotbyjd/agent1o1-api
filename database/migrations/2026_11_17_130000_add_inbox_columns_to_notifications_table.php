<?php

use App\Enums\Notifications\NotificationEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promotes what the inbox filters, sorts and groups on out of the JSON `data`
 * blob into indexed columns, and backfills them for rows already stored.
 *
 * `occurred_at` is when the latest occurrence happened: a grouped notification
 * moves back to the top of the inbox when it repeats, while `created_at` stays
 * the first occurrence.
 *
 * The backfill also repairs follow-up reminders, which were stored with
 * `subject`/`message` instead of the `title`/`body` every other notification
 * has, and so showed up blank in the bell.
 */
return new class extends Migration
{
    private const string LEGACY_FOLLOW_UP_TYPE = 'assistant_follow_up';

    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->uuid('workspace_id')->nullable()->after('notifiable_id');
            $table->string('event_key')->nullable()->after('type');
            $table->string('category', 40)->nullable()->after('event_key');
            $table->string('severity', 20)->nullable()->after('category');
            $table->string('group_key')->nullable()->after('severity');
            $table->unsignedInteger('group_count')->default(1)->after('group_key');
            $table->timestamp('occurred_at')->nullable()->after('read_at');
            $table->timestamp('archived_at')->nullable()->after('occurred_at');

            $table->index(['notifiable_type', 'notifiable_id', 'archived_at', 'occurred_at'], 'notifications_inbox_index');
            $table->index(['notifiable_id', 'read_at'], 'notifications_unread_index');
            $table->index(['notifiable_id', 'group_key'], 'notifications_group_index');
            $table->index('workspace_id');
            $table->index('occurred_at');
        });

        DB::table('notifications')->orderBy('id')->chunkById(500, function ($notifications): void {
            foreach ($notifications as $notification) {
                $payload = json_decode((string) $notification->data, true);
                $payload = is_array($payload) ? $payload : [];

                if (($payload['type'] ?? null) === self::LEGACY_FOLLOW_UP_TYPE) {
                    $payload = $this->repairedFollowUp($payload);
                }

                $event = is_string($payload['type'] ?? null) ? NotificationEvent::tryFrom($payload['type']) : null;
                $workspaceId = $payload['workspace_id'] ?? null;

                DB::table('notifications')->where('id', $notification->id)->update([
                    'data' => json_encode($payload),
                    'workspace_id' => is_string($workspaceId) && $workspaceId !== '' ? $workspaceId : null,
                    'event_key' => $event?->value,
                    'category' => $event?->definition()->category->value,
                    'severity' => $event?->definition()->severity->value,
                    'occurred_at' => $notification->created_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_inbox_index');
            $table->dropIndex('notifications_unread_index');
            $table->dropIndex('notifications_group_index');
            $table->dropIndex(['workspace_id']);
            $table->dropIndex(['occurred_at']);
            $table->dropColumn(['workspace_id', 'event_key', 'category', 'severity', 'group_key', 'group_count', 'occurred_at', 'archived_at']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function repairedFollowUp(array $payload): array
    {
        $subject = is_string($payload['subject'] ?? null) && $payload['subject'] !== '' ? $payload['subject'] : 'an email thread';

        return [
            'workspace_id' => $payload['workspace_id'] ?? null,
            'type' => NotificationEvent::AssistantFollowUp->value,
            'title' => "No reply yet: {$subject}",
            'body' => $payload['message'] ?? "Nobody has replied to \"{$subject}\" yet.",
            'data' => array_filter([
                'follow_up_id' => $payload['follow_up_id'] ?? null,
                'subject' => $payload['subject'] ?? null,
            ], fn (mixed $value): bool => $value !== null),
        ];
    }
};
