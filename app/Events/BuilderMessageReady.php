<?php

namespace App\Events;

use App\Http\Resources\V1\WorkflowBuilder\WorkflowBuilderDraftVersionResource;
use App\Http\Resources\V1\WorkflowBuilder\WorkflowBuilderMessageResource;
use App\Models\WorkflowBuilderDraftVersion;
use App\Models\WorkflowBuilderMessage;
use App\Models\WorkflowBuilderSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BuilderMessageReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly WorkflowBuilderSession $session,
        public readonly WorkflowBuilderMessage $message,
        public readonly ?WorkflowBuilderDraftVersion $version = null,
        public readonly bool $error = false,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("builder.session.{$this->session->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'builder.message.ready';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => new WorkflowBuilderMessageResource($this->message),
            'draft' => [
                'nodes' => $this->session->nodes_draft ?? [],
                'edges' => $this->session->edges_draft ?? [],
            ],
            'version' => $this->version ? new WorkflowBuilderDraftVersionResource($this->version) : null,
            'session' => [
                'id' => $this->session->id,
                'title' => $this->session->title,
                'status' => $this->session->status->value,
            ],
            'error' => $this->error,
        ];
    }
}
