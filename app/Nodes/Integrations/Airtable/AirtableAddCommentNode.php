<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AirtableAddCommentNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_add_comment';
    }

    public function name(): string
    {
        return 'Airtable: Add Comment';
    }

    public function description(): string
    {
        return 'Comments on a record.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['base_id', 'table', 'record_id', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'base_id' => $this->baseField(),
                'table' => $this->tableField(),
                'record_id' => Field::text('Record', 'The record id (starts with rec), from a previous step.', 'recAbc123def456'),
                'text' => Field::textarea('Comment', 'Use @[usrId] to mention someone.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->recordId($config);
        $comment = $this->airtable($run, $config, 'POST', $this->tablePath($config)."/{$id}/comments", [], ['text' => $this->requiredText($config, 'text', 'The comment')]);

        return ['id' => $comment['id'] ?? null, 'record_id' => $id, 'text' => $comment['text'] ?? null, 'author' => data_get($comment, 'author.email'), 'created_at' => $comment['createdTime'] ?? null];
    }
}
