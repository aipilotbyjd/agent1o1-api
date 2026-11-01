<?php

namespace App\Events\Runs;

use App\Broadcasting\BroadcastsReliably;
use App\Broadcasting\Channels;
use App\Models\Runs\Run;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A `Run` entered a new status. Fired from `RunObserver` rather than from the
 * engine, so every path that moves a run: `StartWorkflowRunAction`,
 * `GraphAdvancer::finishIfDone()`, `StepFailureHandler::failRun()`,
 * `WorkflowRunner::cancel()`, `AgentRunner`: publishes without having to
 * remember to.
 *
 * Distinct from the domain events `RunCompleted`/`RunFailed`/`RunCancelled`,
 * which exist for server-side listeners (billing, notifications) and carry
 * the whole model. This one is the wire format: a small, stable payload aimed
 * at a browser.
 */
class RunStateChanged implements ShouldBroadcast
{
    use BroadcastsReliably, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The state at the moment the transition happened.
     *
     * Captured in the constructor rather than read in `broadcastWith()`:
     * broadcasts are queued, `SerializesModels` re-fetches the model when the
     * job runs, and a run that moved pending → running → completed in
     * milliseconds would otherwise broadcast "completed" three times and
     * never show the states in between.
     *
     * @var array<string, mixed>
     */
    private array $payload;

    public function __construct(public readonly Run $run)
    {
        $this->payload = [
            'id' => $run->id,
            'workflow_id' => $run->workflow_id,
            'runnable_type' => $run->runnable_type,
            'runnable_id' => $run->runnable_id,
            'status' => $run->status->value,
            'error' => $run->error,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'emitted_at' => $this->emittedAt(),
        ];
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(Channels::workspaceRuns($this->run->workspace_id)),
            new PrivateChannel(Channels::run($this->run)),
        ];
    }

    public function broadcastAs(): string
    {
        return 'run.state-changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
