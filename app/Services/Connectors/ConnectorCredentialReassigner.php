<?php

namespace App\Services\Connectors;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Agents\AgentToolBinding;
use App\Models\Agents\KnowledgeSource;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves everything pinned to one connection onto another before the first
 * is disconnected: draft workflow nodes, each workflow's live (published)
 * graph (which is what runs execute) agent tools and knowledge sources.
 * If the old connection was the default, the new one takes that over so
 * steps that don't pin an account keep resolving.
 */
class ConnectorCredentialReassigner
{
    public function __construct(private readonly ConnectorCredentialUsage $usage) {}

    /**
     * @return array{workflows: int, agent_tools: int, knowledge_sources: int}
     *
     * @throws ValidationException when the replacement can't stand in for the original
     */
    public function reassign(ConnectorCredential $from, ConnectorCredential $to): array
    {
        $this->ensureCompatible($from, $to);
        $wasDefault = $this->usage->isEffectiveDefault($from);

        return DB::transaction(function () use ($from, $to, $wasDefault): array {
            $workflowIds = $this->reassignWorkflowNodes($from, $to);
            $workflowIds = array_unique([...$workflowIds, ...$this->reassignPublishedGraphs($from, $to)]);

            $agentTools = AgentToolBinding::query()
                ->whereHas('agent', fn (Builder $agent) => $agent->where('workspace_id', $from->workspace_id))
                ->where('config->credential_id', $from->id)
                ->get()
                ->each(fn (AgentToolBinding $binding) => $binding->update([
                    'config' => [...$binding->config, 'credential_id' => $to->id],
                ]))
                ->count();

            $knowledgeSources = KnowledgeSource::query()
                ->where('workspace_id', $from->workspace_id)
                ->where('connector_credential_id', $from->id)
                ->update(['connector_credential_id' => $to->id]);

            if ($wasDefault) {
                $to->markAsDefault();
            }

            return [
                'workflows' => count($workflowIds),
                'agent_tools' => $agentTools,
                'knowledge_sources' => $knowledgeSources,
            ];
        });
    }

    /**
     * Same workspace and app; and a team connection can only be replaced by
     * another team one, since a personal account is unusable by everyone
     * else whose runs relied on it.
     */
    private function ensureCompatible(ConnectorCredential $from, ConnectorCredential $to): void
    {
        $message = match (true) {
            $from->is($to) => 'Pick a different account to move to.',
            $from->workspace_id !== $to->workspace_id, $from->connector_id !== $to->connector_id => 'The replacement must be another account for the same app.',
            $from->scope === ConnectorCredentialScope::Team && $to->scope !== ConnectorCredentialScope::Team => 'A shared account can only be replaced by another shared account.',
            ! $to->isUsable() => 'The replacement account has expired. Reconnect it first.',
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['replace_with' => $message]);
        }
    }

    /**
     * @return array<int, string> ids of the workflows touched
     */
    private function reassignWorkflowNodes(ConnectorCredential $from, ConnectorCredential $to): array
    {
        $nodes = WorkflowNode::query()
            ->whereHas('workflow', fn (Builder $workflow) => $workflow->where('workspace_id', $from->workspace_id))
            ->where('config->credential_id', $from->id)
            ->get();

        $nodes->each(fn (WorkflowNode $node) => $node->update([
            'config' => [...$node->config, 'credential_id' => $to->id],
        ]));

        return $nodes->pluck('workflow_id')->unique()->values()->all();
    }

    /**
     * Rewrites only each workflow's current version: older versions are
     * history, and rolling back to one is a deliberate act.
     *
     * @return array<int, string> ids of the workflows touched
     */
    private function reassignPublishedGraphs(ConnectorCredential $from, ConnectorCredential $to): array
    {
        $touched = [];

        Workflow::query()
            ->where('workspace_id', $from->workspace_id)
            ->whereNotNull('current_version_id')
            ->with('currentVersion')
            ->each(function (Workflow $workflow) use ($from, $to, &$touched): void {
                $version = $workflow->currentVersion;
                $graph = $version?->graph;

                if (! is_array($graph) || ! is_array($graph['nodes'] ?? null)) {
                    return;
                }

                $changed = false;
                $graph['nodes'] = array_map(function (array $node) use ($from, $to, &$changed): array {
                    if (($node['config']['credential_id'] ?? null) !== $from->id) {
                        return $node;
                    }
                    $changed = true;
                    $node['config']['credential_id'] = $to->id;

                    return $node;
                }, $graph['nodes']);

                if ($changed) {
                    $version->forceFill(['graph' => $graph])->save();
                    $touched[] = $workflow->id;
                }
            });

        return $touched;
    }
}
