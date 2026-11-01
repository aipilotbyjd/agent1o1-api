<?php

namespace App\Nodes\AiAutomation;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use App\Nodes\Support\Field;
use App\Services\Agents\KnowledgeBase;
use App\Services\Agents\UntrustedContent;

/**
 * Searches the workspace knowledge base from a workflow: the same hybrid
 * (meaning plus exact words) ranking agents use, over the shared knowledge
 * only. A member's private Brain is never visible to a workflow, because a
 * run is not that member.
 */
class KnowledgeSearchNode implements DeclaresEffect, DeclaresOutputSchema, HasIcon, NodeContract
{
    public function __construct(private readonly KnowledgeBase $knowledgeBase) {}

    public function type(): string
    {
        return 'knowledge_search';
    }

    public function category(): string
    {
        return NodeCategory::AiAutomation->value;
    }

    public function name(): string
    {
        return 'Knowledge Search';
    }

    public function icon(): string
    {
        return 'book-open-01';
    }

    public function description(): string
    {
        return 'Searches your knowledge base for the passages that best answer a question, with their sources, ready to hand to an AI step.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                'query' => Field::textarea('What to look for', 'A question or keywords. Use {{templates}} to take it from an earlier step.'),
                'collection' => Field::text('Collection', 'Optional. Search only this collection of the knowledge base.', 'help-center'),
                'max_results' => Field::integer('Most passages', 'How many passages to return.', 5, 1, 20),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'results' => ['title' => 'Passages', 'description' => 'The matches, best first: text, source, collection and score.'],
                'context' => ['title' => 'Context', 'description' => 'The passages as one block of text to put in an AI prompt. Marked as outside content, so the AI treats it as data.'],
                'count' => ['title' => 'Count', 'description' => 'How many passages matched.'],
                'found' => ['title' => 'Found', 'description' => 'True when at least one passage matched.'],
                'result' => ['title' => 'Branch', 'description' => 'found or none, so an edge can follow it.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = trim((string) ($config['query'] ?? ''));

        if ($query === '') {
            throw new \InvalidArgumentException('What to look for is empty.');
        }

        $workspace = Workspace::query()->findOrFail($run->workspace_id);
        $collection = filled($config['collection'] ?? null) ? trim((string) $config['collection']) : null;
        $limit = max(1, min(20, (int) ($config['max_results'] ?? 5)));

        $results = $this->knowledgeBase->search($workspace, $query, $collection, $limit, excludeArtifactCollections: true)
            ->map(fn (array $hit): array => [
                'text' => $hit['text'],
                'source' => $hit['source'],
                'collection' => $hit['collection'],
                'url' => $hit['url'],
                'score' => round($hit['score'], 4),
            ])
            ->values()
            ->all();

        return [
            'results' => $results,
            'context' => $results === [] ? '' : UntrustedContent::wrap('knowledge_base', implode("\n\n---\n\n", array_map(
                fn (array $hit): string => ($hit['source'] !== null ? "[{$hit['source']}]\n" : '').$hit['text'],
                $results,
            ))),
            'count' => count($results),
            'found' => $results !== [],
            'result' => $results === [] ? 'none' : 'found',
        ];
    }
}
