<?php

namespace App\Events;

use App\Models\Execution;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExecutionFailedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Execution $execution,
        public readonly string $errorMessage,
    ) {}

    public function broadcastOn(): array|Channel
    {
        return new PrivateChannel("execution.{$this->execution->id}");
    }

    public function broadcastAs(): string
    {
        return 'execution.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'error' => $this->errorMessage,
            'finished_at' => $this->execution->finished_at?->toISOString(),
        ];
    }
}
