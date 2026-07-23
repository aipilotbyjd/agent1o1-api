<?php

namespace App\Events;

use App\Engine\NodeResult;
use App\Models\Run;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NodeCompletedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Run $execution,
        public readonly string $nodeId,
        public readonly NodeResult $result,
        public readonly int $sequence,
    ) {}

    public function broadcastOn(): array|Channel
    {
        return new PrivateChannel("execution.{$this->execution->id}");
    }

    public function broadcastAs(): string
    {
        return 'node.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'node_id' => $this->nodeId,
            'status' => $this->result->status->value,
            'input' => $this->result->input,
            'output' => $this->result->output,
            'error' => $this->result->error,
            'duration_ms' => $this->result->durationMs,
            'sequence' => $this->sequence,
        ];
    }
}
