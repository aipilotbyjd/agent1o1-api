<?php

namespace App\Jobs;

use App\Agents\Internal\Workflow\WorkflowNamingAgent;
use App\Agents\Internal\Workflow\WorkflowRefinementAgent;
use App\Events\BuilderMessageReady;
use App\Models\AiGenerationLog;
use App\Models\User;
use App\Models\WorkflowBuilderMessage;
use App\Models\WorkflowBuilderSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Throwable;

class ProcessBuilderMessageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public array $backoff = [5, 30];

    public function __construct(
        private readonly WorkflowBuilderSession $session,
        private readonly User $user,
        private readonly WorkflowBuilderMessage $userMessage,
        private readonly WorkflowBuilderMessage $assistantMessage,
    ) {
        $this->onQueue('builder-ai');
    }

    public function handle(): void
    {
        $this->assistantMessage->update(['processing_status' => 'processing']);

        $previousNodes = $this->session->nodes_draft ?? [];
        $previousEdges = $this->session->edges_draft ?? [];

        $agent = new WorkflowRefinementAgent($this->session);

        // Stream + broadcast each token/tool-call event on the session's private
        // channel as it happens, instead of waiting for the whole reply — the
        // frontend appends text_delta chunks live and shows tool_call/tool_result
        // as inline progress (e.g. "Adding node: HTTP Request…").
        //
        // Broadcast manually (not the ->broadcastNow() convenience) so oversized
        // payloads can be trimmed first — some tools (list_available_nodes with
        // no category filter, in particular) can return the entire node catalog,
        // which blows past Reverb/Pusher's ~10KB message limit. The LLM still
        // gets the full result; only the broadcast copy is capped.
        $channel = new PrivateChannel("builder.session.{$this->session->id}");

        $stream = $this->session->conversation_id
            ? $agent->continue($this->session->conversation_id, as: $this->user)
                ->stream($this->userMessage->content)
            : $agent->forUser($this->user)->stream($this->userMessage->content);

        foreach ($stream as $event) {
            $this->broadcastTrimmed($event, $channel);
        }

        $response = $stream;

        if (! $this->session->conversation_id) {
            $conversationId = $response->conversationId ?? $agent->currentConversation();
            if ($conversationId) {
                $this->stampConversation($conversationId);
                $this->session->update(['conversation_id' => $conversationId]);
            }
        }

        $this->session->refresh();

        $latestVersion = $this->session->draftVersions()->first();

        $this->assistantMessage->update([
            'processing_status' => 'completed',
            'content' => $response->text ?? '',
            'draft_version_id' => $latestVersion?->id,
            'actions' => $this->buildActionDiff($previousNodes, $previousEdges),
        ]);

        // Auto-title the session if it still has the default title
        if ($this->session->title === 'Untitled workflow') {
            $this->autoTitle();
        }

        AiGenerationLog::create([
            'workspace_id' => $this->session->workspace_id,
            'created_by' => $this->user->id,
            'type' => 'workflow_refine',
        ]);

        BuilderMessageReady::dispatch(
            $this->session->fresh(),
            $this->assistantMessage->fresh(),
            $latestVersion,
        );
    }

    public function failed(Throwable $exception): void
    {
        $this->assistantMessage->update([
            'processing_status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        BuilderMessageReady::dispatch(
            $this->session->fresh(),
            $this->assistantMessage->fresh(),
            null,
            true,
        );
    }

    private function stampConversation(string $conversationId): void
    {
        Conversation::query()
            ->whereKey($conversationId)
            ->update(['workspace_id' => $this->session->workspace_id]);
    }

    /**
     * Broadcast a stream event, capping `result`/`arguments` if either is too
     * large for the WebSocket transport (e.g. list_available_nodes returning
     * the full catalog). The frontend only needs these fields for a couple of
     * specific tools (AddNodeTool's node_id) — everything else just needs
     * success/failure, so a trimmed payload loses nothing that's actually used.
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

    private function autoTitle(): void
    {
        try {
            $response = (new WorkflowNamingAgent)->prompt($this->userMessage->content);
            $title = $response['title'] ?? null;

            if ($title) {
                $this->session->update(['title' => $title]);
                $this->session->refresh();
            }
        } catch (Throwable) {
            // Non-critical — title stays as "Untitled workflow"
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $previousNodes
     * @param  array<int, array<string, mixed>>  $previousEdges
     * @return array<int, array<string, mixed>>
     */
    private function buildActionDiff(array $previousNodes, array $previousEdges): array
    {
        $currentNodes = $this->session->nodes_draft ?? [];
        $currentEdges = $this->session->edges_draft ?? [];

        $prevById = array_column($previousNodes, null, 'id');
        $currById = array_column($currentNodes, null, 'id');

        $actions = [];

        foreach (array_diff_key($currById, $prevById) as $id => $node) {
            $actions[] = ['type' => 'node_added', 'node_id' => $id, 'node_type' => $node['type'] ?? null, 'label' => $node['name'] ?? $id];
        }

        foreach (array_diff_key($prevById, $currById) as $id => $node) {
            $actions[] = ['type' => 'node_removed', 'node_id' => $id, 'label' => $node['name'] ?? $id];
        }

        foreach (array_intersect_key($currById, $prevById) as $id => $node) {
            $prev = $prevById[$id];
            $configChanged = json_encode($node['config'] ?? []) !== json_encode($prev['config'] ?? []);
            $nameChanged = ($node['name'] ?? '') !== ($prev['name'] ?? '');
            if ($configChanged || $nameChanged) {
                $actions[] = ['type' => 'node_updated', 'node_id' => $id, 'label' => $node['name'] ?? $id];
            }
        }

        $edgeDelta = count($currentEdges) - count($previousEdges);
        if ($edgeDelta > 0) {
            $actions[] = ['type' => 'edges_added', 'count' => $edgeDelta];
        } elseif ($edgeDelta < 0) {
            $actions[] = ['type' => 'edges_removed', 'count' => abs($edgeDelta)];
        }

        return $actions;
    }
}
