<?php

namespace App\Events;

use App\Models\Run;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExecutionStartedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Run $execution) {}

    public function broadcastOn(): array|Channel
    {
        return new PrivateChannel("execution.{$this->execution->id}");
    }

    public function broadcastAs(): string
    {
        return 'execution.started';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'workflow_id' => $this->execution->workflow_id,
            'mode' => $this->execution->mode,
            'started_at' => $this->execution->started_at?->toISOString(),
        ];
    }
}
