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
use InvalidArgumentException;

/**
 * Adds text to the shared knowledge base, so a workflow can feed it: save
 * every solved ticket, every meeting summary, every published page. Saving
 * under a source that already exists replaces it instead of piling up
 * copies.
 */
class KnowledgeSaveNode implements DeclaresEffect, DeclaresOutputSchema, HasIcon, NodeContract
{
    public function __construct(private readonly KnowledgeBase $knowledgeBase) {}

    public function type(): string
    {
        return 'knowledge_save';
    }

    public function category(): string
    {
        return NodeCategory::AiAutomation->value;
    }

    public function name(): string
    {
        return 'Knowledge Save';
    }

    public function icon(): string
    {
        return 'bookmark-add-01';
    }

    public function description(): string
    {
        return 'Adds text to your knowledge base so agents and Knowledge Search can find it. Saving the same source again replaces it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['text', 'source'],
            'properties' => [
                'text' => Field::textarea('Text to save', 'The document, answer or notes. Use {{templates}} to take it from an earlier step.'),
                'source' => Field::text('Name', 'What this is called, e.g. a title or ticket number. Saving the same name again replaces the earlier version.', 'Refund policy'),
                'collection' => Field::text('Collection', 'Group it with related documents.', 'workflows'),
                'replace' => Field::advanced(Field::boolean('Replace an earlier version', 'Swap the text previously saved under this name instead of adding to it.', true)),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'source' => ['title' => 'Name', 'description' => 'The name it was saved under.'],
                'collection' => ['title' => 'Collection', 'description' => 'The collection it was saved in.'],
                'chunks' => ['title' => 'Passages', 'description' => 'How many searchable passages it was split into.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $text = trim((string) ($config['text'] ?? ''));
        $source = trim((string) ($config['source'] ?? ''));

        if ($text === '' || $source === '') {
            throw new InvalidArgumentException('Both the text and its name are needed to save to the knowledge base.');
        }

        $collection = filled($config['collection'] ?? null) ? trim((string) $config['collection']) : 'workflows';

        $chunks = $this->knowledgeBase->ingest(
            Workspace::query()->findOrFail($run->workspace_id),
            $text,
            $source,
            $collection,
            ['saved_by' => 'workflow', 'run_id' => $run->id],
            replaceSource: (bool) ($config['replace'] ?? true),
        );

        return ['source' => $source, 'collection' => $collection, 'chunks' => $chunks->count()];
    }
}
