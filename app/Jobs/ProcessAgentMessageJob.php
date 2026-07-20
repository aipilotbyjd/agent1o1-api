<?php

namespace App\Jobs;

use App\Agents\AgentRunner;
use App\Events\AgentMessageReady;
use App\Models\Agent;
use App\Models\AgentMessageRequest;
use App\Models\AgentRun;
use App\Models\Artifact;
use App\Models\Credential;
use App\Models\User;
use App\Services\AgentRunRecorder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Throwable;

/**
 * Runs an agent's reply in the background and streams it live over the
 * `agent.stream.{requestId}` private channel — mirrors ProcessBuilderMessageJob's
 * pattern for the workflow builder, adapted to standalone Agent conversations.
 * `$request->id` doubles as the channel key, so it's always a real,
 * ownership-checkable row (see routes/channels.php).
 */
class ProcessAgentMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public array $backoff = [5, 30];

    public function __construct(
        private readonly Agent $agent,
        private readonly User $user,
        private readonly string $message,
        private readonly AgentMessageRequest $request,
    ) {
        $this->onQueue('agent-ai');
        $this->timeout = max(60, $agent->timeout_seconds + 30);
    }

    public function handle(AgentRunner $agentRunner, AgentRunRecorder $recorder): void
    {
        $this->request->update(['status' => 'processing']);

        $run = $recorder->start($this->agent, [
            'user_id' => $this->user->id,
            'conversation_id' => $this->request->conversation_id,
            'source' => 'conversation',
            'input' => $this->message,
        ]);
        $this->request->update(['agent_run_id' => $run->id]);

        $channel = new PrivateChannel("agent.stream.{$this->request->id}");

        $existingConversationId = $this->request->conversation_id;

        $workflowAgent = $agentRunner->build($this->agent, $this->message, [
            'agent' => $this->agent,
            'credentials' => $this->loadCredentials(),
            'user_id' => $this->user->id,
            'conversation_id' => $existingConversationId,
            'agent_run_id' => $run->id,
        ]);

        $conversable = $existingConversationId
            ? $workflowAgent->continue($existingConversationId, as: $this->user)
            : $workflowAgent->forUser($this->user);

        $stream = $conversable->stream(
            $this->message,
            provider: $this->agent->provider,
            model: $this->agent->model,
        );

        foreach ($stream as $event) {
            $this->broadcastTrimmed($event, $channel);
            $this->recordToolStep($recorder, $run, $event);
            $this->broadcastArtifact($event, $channel);
        }

        $conversationId = $stream->conversationId ?? $workflowAgent->currentConversation();

        if ($conversationId && ! $existingConversationId) {
            $this->stampConversation($conversationId);
            $run->update(['conversation_id' => $conversationId]);

            // Artifacts exported before the conversation id was known (the first
            // turn) were recorded with a null conversation_id — backfill them so
            // later turns in this same conversation can find them for versioning.
            Artifact::where('agent_run_id', $run->id)
                ->whereNull('conversation_id')
                ->update(['conversation_id' => $conversationId]);
        }

        $recorder->complete($run, (string) ($stream->text ?? ''), $this->extractUsage($stream));

        $this->request->update([
            'status' => 'completed',
            'conversation_id' => $conversationId,
        ]);

        AgentMessageReady::dispatch(
            $this->request->id,
            $conversationId,
            (string) ($stream->text ?? ''),
            $this->agent,
        );
    }

    public function failed(Throwable $exception): void
    {
        $this->request->refresh();
        $this->request->update(['status' => 'failed']);

        if ($this->request->agent_run_id && $run = AgentRun::find($this->request->agent_run_id)) {
            app(AgentRunRecorder::class)->fail($run, $exception);
        }

        AgentMessageReady::dispatch(
            $this->request->id,
            $this->request->conversation_id,
            '',
            $this->agent,
            error: true,
            errorMessage: $this->agentFailureMessage($exception),
        );
    }

    /** Mirrors AgentConversationController::agentFailureMessage — surfaces the provider's own rejection reason. */
    private function agentFailureMessage(Throwable $e): string
    {
        if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
            $body = $e->response->json('error.message') ?? $e->response->body();

            return "The agent's model provider rejected the request: {$body}";
        }

        return 'The agent failed to respond. Please try again.';
    }

    /**
     * Broadcast a stream event, capping `result`/`arguments` if either is too
     * large for the WebSocket transport — mirrors ProcessBuilderMessageJob.
     */
    private function broadcastTrimmed(StreamEvent $event, PrivateChannel $channel): void
    {
        $payload = $event->toArray();

        foreach (['result', 'arguments'] as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null) {
                continue;
            }

            $isString = is_string($payload[$key]);
            $encoded = $isString ? $payload[$key] : json_encode($payload[$key]);

            if ($encoded !== false && strlen($encoded) > 3000) {
                $payload[$key] = $isString
                    ? json_encode(['truncated' => true])
                    : ['truncated' => true];
            }
        }

        Broadcast::on($channel)->as($event->type())->with($payload)->sendNow();
    }

    /**
     * When the ExportArtifactTool produces a result, broadcast it as its own
     * event on the same channel so the frontend can render a rich artifact
     * card immediately instead of waiting for the run to finish.
     */
    private function broadcastArtifact(StreamEvent $event, PrivateChannel $channel): void
    {
        $payload = $event->toArray();
        $toolName = $payload['name'] ?? $payload['tool'] ?? $payload['toolName'] ?? null;

        if ($toolName !== 'ExportArtifactTool' || ! array_key_exists('result', $payload) || $payload['result'] === null) {
            return;
        }

        $result = is_string($payload['result']) ? json_decode($payload['result'], true) : $payload['result'];

        if (! is_array($result) || isset($result['error'])) {
            return;
        }

        Broadcast::on($channel)->as('artifact')->with($result)->sendNow();
    }

    /**
     * Persist a trace step for tool-call stream events so the run history shows
     * which tools the agent invoked and with what input/output. Non-tool events
     * (text deltas, etc.) are ignored.
     */
    private function recordToolStep(AgentRunRecorder $recorder, AgentRun $run, StreamEvent $event): void
    {
        $type = $event->type();

        if (! str_contains($type, 'tool')) {
            return;
        }

        $payload = $event->toArray();

        // Only record once a tool call carries a result — that event has both
        // the arguments and the output, giving a complete step.
        if (! array_key_exists('result', $payload) || $payload['result'] === null) {
            return;
        }

        $recorder->recordStep($run, [
            'action' => 'tool_call',
            'tool_name' => $payload['name'] ?? $payload['tool'] ?? $payload['toolName'] ?? null,
            'tool_input' => $this->arrayable($payload['arguments'] ?? null),
            'tool_output' => $this->arrayable($payload['result'] ?? null),
        ]);
    }

    /**
     * Normalise a stream payload value to something JSON-castable for storage.
     */
    private function arrayable(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? $value : ['value' => $value];
    }

    /**
     * Best-effort token usage extraction — the streaming response exposes usage
     * differently across providers, so probe defensively and normalise.
     *
     * @return array<string, int|null>
     */
    private function extractUsage(object $stream): array
    {
        $usage = property_exists($stream, 'usage') ? $stream->usage : null;

        if ($usage === null) {
            return [];
        }

        $usage = is_array($usage) ? $usage : (array) $usage;

        $prompt = $usage['prompt_tokens'] ?? $usage['promptTokens'] ?? $usage['input_tokens'] ?? null;
        $completion = $usage['completion_tokens'] ?? $usage['completionTokens'] ?? $usage['output_tokens'] ?? null;
        $total = $usage['total_tokens'] ?? $usage['totalTokens'] ?? null;

        if ($total === null && ($prompt !== null || $completion !== null)) {
            $total = (int) $prompt + (int) $completion;
        }

        return [
            'prompt_tokens' => $prompt !== null ? (int) $prompt : null,
            'completion_tokens' => $completion !== null ? (int) $completion : null,
            'total_tokens' => $total !== null ? (int) $total : null,
        ];
    }

    private function stampConversation(string $conversationId): void
    {
        Conversation::query()
            ->whereKey($conversationId)
            ->update([
                'agent_id' => $this->agent->id,
                'workspace_id' => $this->agent->workspace_id,
            ]);
    }

    /**
     * Only load credentials for node types the agent's tools actually require —
     * loading the entire workspace credential store would expose unrelated
     * secrets to every agent run. Mirrors RunAgentJob's scoping.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadCredentials(): array
    {
        $this->agent->loadMissing('toolConfigs');

        $neededTypes = $this->agent->toolConfigs
            ->where('is_enabled', true)
            ->pluck('node_type')
            ->unique()
            ->values()
            ->all();

        return Credential::query()
            ->where('workspace_id', $this->agent->workspace_id)
            ->when(! empty($neededTypes), fn ($q) => $q->whereIn('type', $neededTypes))
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->first()->getDecryptedData())
            ->all();
    }
}
