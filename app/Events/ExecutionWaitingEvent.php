<?php

namespace App\Events;

use App\Engine\ExecutionPause;
use App\Models\Run;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExecutionWaitingEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Run $execution,
        public readonly ExecutionPause $pause,
    ) {}

    public function broadcastOn(): array|Channel
    {
        return new PrivateChannel("execution.{$this->execution->id}");
    }

    public function broadcastAs(): string
    {
        return 'execution.waiting';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'reason' => $this->pause->reason,
            'resume_at' => $this->pause->resumeAt->toISOString(),
            'webhook_wait_uuid' => $this->pause->webhookWaitUuid,
        ];
    }
}
