<?php

namespace App\Events\Runs;

use App\Broadcasting\BroadcastsReliably;
use App\Broadcasting\Channels;
use App\Models\Runs\NodeRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One step of a run changed state: what lights a node up on an open canvas.
 * Broadcast only on the run's own channel, never the workspace-wide one: a
 * fan-out graph can settle dozens of nodes a second, which a runs *list* has
 * no use for.
 *
 * `output` is deliberately excluded: it can be megabytes (an API node's
 * response body) and is fetched on demand from the node-run detail endpoint
 * when a node is actually opened.
 */
class NodeRunStateChanged implements ShouldBroadcast
{
    use BroadcastsReliably, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Snapshotted at construction for the same reason as
     * `RunStateChanged`: a queued broadcast re-fetches its model, and a node
     * that went running → completed before the job ran would never be seen
     * running.
     *
     * @var array<string, mixed>
     */
    private array $payload;

    public function __construct(public readonly NodeRun $nodeRun)
    {
        $this->payload = [
            'id' => $nodeRun->id,
            'run_id' => $nodeRun->run_id,
            'key' => $nodeRun->key,
            'type' => $nodeRun->type,
            'status' => $nodeRun->status->value,
            'attempt' => $nodeRun->attempt,
            'error' => $nodeRun->error,
            'started_at' => $nodeRun->started_at?->toIso8601String(),
            'finished_at' => $nodeRun->finished_at?->toIso8601String(),
            'emitted_at' => $this->emittedAt(),
        ];
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Channels::run($this->nodeRun->run))];
    }

    public function broadcastAs(): string
    {
        return 'node-run.state-changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
