<?php

namespace App\Jobs;

use App\Agents\Internal\WorkflowNamingAgent;
use App\Agents\Internal\WorkflowRefinementAgent;
use App\Events\BuilderMessageReady;
use App\Models\AiGenerationLog;
use App\Models\User;
use App\Models\WorkflowBuilderMessage;
use App\Models\WorkflowBuilderSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Models\Conversation;
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

        if ($this->session->conversation_id) {
            $response = $agent->continue($this->session->conversation_id, as: $this->user)
                ->prompt($this->userMessage->content);
        } else {
            $response = $agent->forUser($this->user)->prompt($this->userMessage->content);

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
            'content' => (string) $response,
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
