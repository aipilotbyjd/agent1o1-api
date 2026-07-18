<?php

namespace App\Jobs;

use App\Agents\AgentRunner;
use App\Events\AgentMessageReady;
use App\Models\Agent;
use App\Models\AgentMessageRequest;
use App\Models\Credential;
use App\Models\User;
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

    public function handle(AgentRunner $agentRunner): void
    {
        $this->request->update(['status' => 'processing']);

        $channel = new PrivateChannel("agent.stream.{$this->request->id}");

        $workflowAgent = $agentRunner->build($this->agent, $this->message, [
            'agent' => $this->agent,
            'credentials' => $this->loadCredentials(),
        ]);

        $existingConversationId = $this->request->conversation_id;

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
        }

        $conversationId = $stream->conversationId ?? $workflowAgent->currentConversation();

        if ($conversationId && ! $existingConversationId) {
            $this->stampConversation($conversationId);
        }

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
        $this->request->update(['status' => 'failed']);

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
