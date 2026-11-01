<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionGetDatabaseNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_get_database';
    }

    public function name(): string
    {
        return 'Notion: Get Database';
    }

    public function description(): string
    {
        return 'Gets a database with its columns and their choices.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['database'],
            'properties' => [
                ...$this->credentialFields(),
                'database' => Field::dynamic('Database', 'notion.databases', 'Pick a database, or paste its link or id.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeDatabase($this->get($run, '/databases/'.$this->id($config, 'database', 'The database'), $config));
    }
}
