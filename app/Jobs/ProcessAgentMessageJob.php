<?php

namespace App\Jobs;

use App\Agents\AgentRunner;
use App\Events\AgentMessageReady;
use App\Models\Agent;
use App\Models\AgentMessageRequest;
use App\Models\Artifact;
use App\Models\Credential;
use App\Models\Run;
use App\Models\User;
use App\Services\Agent\AgentBudgetService;
use App\Services\Agent\AgentGuardrailService;
use App\Services\Agent\AgentMemoryService;
use App\Services\Agent\AgentReasoningService;
use App\Services\AgentRunRecorder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
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

    /**
     * Tool calls captured during the stream, fed into the reflection pass.
     *
     * @var list<array{tool: ?string, output: mixed}>
     */
    private array $toolContext = [];

    public function __construct(
        private readonly Agent $agent,
        private readonly User $user,
        private readonly string $message,
        private readonly AgentMessageRequest $request,
    ) {
        $this->onQueue('agent-ai');
        $this->timeout = max(60, $agent->timeout_seconds + 30);
    }

    public function handle(
        AgentRunner $agentRunner,
        AgentRunRecorder $recorder,
        AgentBudgetService $budgets,
        AgentGuardrailService $guardrails,
        AgentReasoningService $reasoning,
        AgentMemoryService $memory,
    ): void {
        $this->request->update(['status' => 'processing']);

        $channel = new PrivateChannel("agent.stream.{$this->request->id}");

        // Cost & rate guardrails (roadmap item 11): refuse to start a run when the
        // agent is paused or has burned its daily budget.
        if ($blockReason = $budgets->blockReason($this->agent)) {
            $this->haltGracefully($channel, $blockReason);

            return;
        }

        // Input safety guardrail (roadmap item 13).
        $inputCheck = $guardrails->checkInput($this->agent, $this->message);
        if ($inputCheck && $inputCheck['block']) {
            $this->haltGracefully($channel, $guardrails->blockedMessage('input', $inputCheck));

            return;
        }

        $run = $recorder->start($this->agent, [
            'user_id' => $this->user->id,
            'conversation_id' => $this->request->conversation_id,
            'source' => 'conversation',
            'input' => $this->message,
        ]);
        $this->request->update(['agent_run_id' => $run->id]);

        $existingConversationId = $this->request->conversation_id;

        $context = [
            'agent' => $this->agent,
            'credentials' => $this->loadCredentials(),
            'user_id' => $this->user->id,
            'conversation_id' => $existingConversationId,
            'agent_run_id' => $run->id,
        ];

        // Planner/executor split (roadmap item 1): draft a plan up front, persist
        // it on the run, and feed it to the executor via context.
        $plan = $agentRunner->plan($this->agent, $this->message, $context);
        if ($plan) {
            $context['plan'] = $plan;
            $run->update(['plan' => $plan]);
            Broadcast::on($channel)->as('plan')->with($plan)->sendNow();
        }

        $workflowAgent = $agentRunner->build($this->agent, $this->message, $context);

        $conversable = $existingConversationId
            ? $workflowAgent->continue($existingConversationId, as: $this->user)
            : $workflowAgent->forUser($this->user);

        $stream = $conversable->stream(
            $this->message,
            provider: $this->agent->provider,
            model: $this->agent->model,
        );

        $this->toolContext = [];

        foreach ($stream as $event) {
            $this->broadcastTrimmed($event, $channel);
            $this->recordToolStep($recorder, $run, $event);
            $this->broadcastArtifact($event, $channel);
        }

        $conversationId = $stream->conversationId ?? $workflowAgent->currentConversation();
        $finalText = (string) ($stream->text ?? '');

        // Reflection / self-correction (roadmap item 2): critique the draft and,
        // when it is judged wrong with low confidence, run one corrective turn.
        $reflection = $reasoning->reflect(
            $this->agent,
            $this->message,
            $plan,
            $this->toolContextSummary(),
            $finalText,
        );

        if ($reflection) {
            $run->update(['reflections' => [$reflection]]);

            if (! $reflection['approved'] && $reflection['confidence'] < 0.5 && trim($reflection['critique']) !== '') {
                $finalText = $this->applyCorrection(
                    $workflowAgent,
                    $conversationId,
                    $reflection['critique'],
                    $finalText,
                    $channel,
                );
            }
        }

        // Output safety guardrail (roadmap item 13).
        $outputCheck = $guardrails->checkOutput($this->agent, $finalText);
        if ($outputCheck && $outputCheck['block']) {
            $finalText = $guardrails->blockedMessage('output', $outputCheck);
            Broadcast::on($channel)->as('guardrail_blocked')->with(['stage' => 'output', 'message' => $finalText])->sendNow();
        }

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

        $recorder->complete($run, $finalText, $this->extractUsage($stream));
        $run->refresh();

        // Cost accounting + budget enforcement (roadmap item 11).
        $budgets->settleRun($this->agent, $run);

        // Automatic long-horizon memory extraction (roadmap item 4).
        if ($this->agent->memory_auto_extract) {
            $memory->extractAndStore($this->agent, $this->message, $finalText, $this->user->id, $run);
        }

        $this->request->update([
            'status' => 'completed',
            'conversation_id' => $conversationId,
        ]);

        AgentMessageReady::dispatch(
            $this->request->id,
            $conversationId,
            $finalText,
            $this->agent,
        );
    }

    /**
     * Run a single corrective turn after reflection rejected the draft. Falls
     * back to the original draft if the correction turn itself fails.
     */
    private function applyCorrection(
        $workflowAgent,
        ?string $conversationId,
        string $critique,
        string $draft,
        PrivateChannel $channel,
    ): string {
        if (! $conversationId) {
            return $draft;
        }

        try {
            Broadcast::on($channel)->as('reflection')->with(['critique' => $critique])->sendNow();

            $corrected = (string) $workflowAgent
                ->continue($conversationId, as: $this->user)
                ->prompt(
                    "A reviewer found issues with your previous answer: {$critique}\n\n"
                    .'Provide a corrected, complete answer. Do not mention this review.',
                    provider: $this->agent->provider,
                    model: $this->agent->model,
                );

            if (trim($corrected) === '') {
                return $draft;
            }

            Broadcast::on($channel)->as('message.corrected')->with(['text' => $corrected])->sendNow();

            return $corrected;
        } catch (Throwable) {
            return $draft;
        }
    }

    /**
     * A compact digest of the tool calls made this run, for the reflection pass.
     */
    private function toolContextSummary(): string
    {
        if ($this->toolContext === []) {
            return '';
        }

        return collect($this->toolContext)
            ->map(function (array $step) {
                $output = is_string($step['output']) ? $step['output'] : json_encode($step['output']);
                $output = mb_substr((string) $output, 0, 800);

                return "- {$step['tool']}: {$output}";
            })
            ->implode("\n");
    }

    /**
     * Mark the request complete with a canned assistant message (budget/guardrail
     * refusals) without ever calling the model.
     */
    private function haltGracefully(PrivateChannel $channel, string $message): void
    {
        Broadcast::on($channel)->as('guardrail_blocked')->with(['message' => $message])->sendNow();

        $this->request->update(['status' => 'completed']);

        AgentMessageReady::dispatch(
            $this->request->id,
            $this->request->conversation_id,
            $message,
            $this->agent,
        );
    }

    public function failed(Throwable $exception): void
    {
        $this->request->refresh();
        $this->request->update(['status' => 'failed']);

        if ($this->request->agent_run_id && $run = Run::find($this->request->agent_run_id)) {
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
        if ($e instanceof RequestException && $e->response) {
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
    private function recordToolStep(AgentRunRecorder $recorder, Run $run, StreamEvent $event): void
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

        $toolName = $payload['name'] ?? $payload['tool'] ?? $payload['toolName'] ?? null;

        $recorder->recordStep($run, [
            'action' => 'tool_call',
            'tool_name' => $toolName,
            'tool_input' => $this->arrayable($payload['arguments'] ?? null),
            'tool_output' => $this->arrayable($payload['result'] ?? null),
        ]);

        $this->toolContext[] = [
            'tool' => $toolName,
            'output' => $payload['result'] ?? null,
        ];
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
