<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class AirtableListCommentsNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_list_comments';
    }

    public function name(): string
    {
        return 'Airtable: List Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on a record, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['base_id', 'table', 'record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'base_id' => $this->baseField(),
                'table' => $this->tableField(),
                'record_id' => Field::text('Record', 'The record id (starts with rec), from a previous step.', 'recAbc123def456'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->airtable($run, $config, 'GET', $this->tablePath($config).'/'.$this->recordId($config).'/comments', ['pageSize' => 100]);
        $comments = array_values(array_filter((array) ($found['comments'] ?? []), 'is_array'));

        return ['comments' => array_map(fn (array $comment): array => ['id' => $comment['id'] ?? null, 'text' => $comment['text'] ?? null, 'author' => data_get($comment, 'author.email'), 'created_at' => $comment['createdTime'] ?? null], $comments), 'count' => count($comments)];
    }
}
