<?php

namespace App\Events\Workflows;

use App\Broadcasting\Channels;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * One step of a builder turn, pushed live to the session's channel while
 * `ProcessWorkflowBuilderMessageJob` runs. Broadcast immediately (not
 * queued): a text delta that arrives after the reply finished is useless.
 *
 * Event names on the wire (`builder.<type>`):
 * - `builder.status`: the assistant message moved to processing/completed/failed.
 * - `builder.delta`: a chunk of assistant text; concatenate in order.
 * - `builder.tool-call`: the assistant called a tool.
 * - `builder.tool-result`: that tool returned (output trimmed).
 * - `builder.draft`: the draft changed; carries the new lock version and
 *                            label, never the graph itself: refetch the session.
 *
 * Reverb and Pusher drop any event over 10 KB, so every string in the
 * payload is capped; the persisted message and draft are the source of truth.
 */
class WorkflowBuilderActivity implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public const int STRING_LIMIT = 2000;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly WorkflowBuilderSession $session,
        public readonly string $messageId,
        public readonly string $type,
        public readonly array $payload = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Channels::workflowBuilderSession($this->session))];
    }

    public function broadcastAs(): string
    {
        return "builder.{$this->type}";
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'message_id' => $this->messageId,
            ...$this->capped($this->payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function capped(array $values): array
    {
        return array_map(function (mixed $value): mixed {
            if (is_array($value)) {
                $encoded = json_encode($value);

                return $encoded !== false && strlen($encoded) > self::STRING_LIMIT
                    ? Str::limit($encoded, self::STRING_LIMIT)
                    : $value;
            }

            return is_string($value) ? Str::limit($value, self::STRING_LIMIT) : $value;
        }, $values);
    }
}
