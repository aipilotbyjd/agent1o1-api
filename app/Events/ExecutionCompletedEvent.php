<?php

namespace App\Events;

use App\Models\Run;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExecutionCompletedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Run $execution) {}

    public function broadcastOn(): array|Channel
    {
        return new PrivateChannel("execution.{$this->execution->id}");
    }

    public function broadcastAs(): string
    {
        return 'execution.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'status' => $this->execution->status,
            'duration_ms' => $this->execution->duration_ms,
            'result_data' => $this->execution->result_data,
            'finished_at' => $this->execution->finished_at?->toISOString(),
        ];
    }
}
