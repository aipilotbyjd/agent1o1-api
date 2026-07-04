<?php

namespace App\Listeners;

use App\Engine\NodeCatalog;
use App\Events\ExecutionCompletedEvent;
use App\Events\ExecutionFailedEvent;
use App\Events\ExecutionStartedEvent;
use App\Events\ExecutionWaitingEvent;
use App\Events\NodeCompletedEvent;
use App\Models\Execution;
use App\Models\ExecutionLog;
use App\Services\ConnectorMetricService;
use App\Services\CredentialMaskingService;
use Illuminate\Events\Dispatcher;

/**
 * Persists a structured audit trail of execution activity to `execution_logs`,
 * masking any sensitive values before they touch storage, and rolls up
 * per-connector call metrics for app (integration) nodes.
 */
class LogExecutionActivity
{
    public function __construct(
        private readonly CredentialMaskingService $masking,
        private readonly ConnectorMetricService $connectorMetrics,
    ) {}

    public function handleStarted(ExecutionStartedEvent $event): void
    {
        $this->write($event->execution, 'info', 'Execution started.');
    }

    public function handleNodeCompleted(NodeCompletedEvent $event): void
    {
        $failed = $event->result->isFailed();

        $this->write(
            $event->execution,
            $failed ? 'error' : 'info',
            $failed ? "Node {$event->nodeId} failed." : "Node {$event->nodeId} completed.",
            [
                'output' => $event->result->output,
                'error' => $event->result->error,
                'duration_ms' => $event->result->durationMs,
                'sequence' => $event->sequence,
            ],
            $event->nodeId,
        );

        $this->recordConnectorMetric($event);
    }

    /**
     * Roll up a daily metric for integration (app) nodes so connector usage
     * and reliability can be reported per workspace.
     */
    private function recordConnectorMetric(NodeCompletedEvent $event): void
    {
        $type = $this->resolveNodeType($event->execution, $event->nodeId);

        if ($type === null || ! NodeCatalog::isAppNode($type)) {
            return;
        }

        $connector = explode('.', $type)[0];

        $this->connectorMetrics->record(
            $event->execution->workspace_id,
            $connector,
            ! $event->result->isFailed(),
            $event->result->durationMs,
        );
    }

    private function resolveNodeType(Execution $execution, string $nodeId): ?string
    {
        $execution->loadMissing('workflow.currentVersion');

        foreach ($execution->workflow?->currentVersion?->nodes_data ?? [] as $node) {
            if (($node['id'] ?? null) === $nodeId) {
                return $node['type'] ?? null;
            }
        }

        return null;
    }

    public function handleWaiting(ExecutionWaitingEvent $event): void
    {
        $this->write($event->execution, 'info', 'Execution paused — waiting to resume.', [
            'reason' => $event->pause->reason,
        ]);
    }

    public function handleCompleted(ExecutionCompletedEvent $event): void
    {
        $this->write($event->execution, 'info', 'Execution completed.', [
            'duration_ms' => $event->execution->duration_ms,
        ]);
    }

    public function handleFailed(ExecutionFailedEvent $event): void
    {
        $this->write($event->execution, 'error', 'Execution failed.', [
            'error' => $event->errorMessage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(Execution $execution, string $level, string $message, array $context = [], ?string $nodeId = null): void
    {
        ExecutionLog::create([
            'execution_id' => $execution->id,
            'workspace_id' => $execution->workspace_id,
            'node_id' => $nodeId,
            'level' => $level,
            'message' => $message,
            'context' => $context === [] ? null : $this->masking->mask($context),
            'logged_at' => now(),
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            ExecutionStartedEvent::class => 'handleStarted',
            NodeCompletedEvent::class => 'handleNodeCompleted',
            ExecutionWaitingEvent::class => 'handleWaiting',
            ExecutionCompletedEvent::class => 'handleCompleted',
            ExecutionFailedEvent::class => 'handleFailed',
        ];
    }
}
