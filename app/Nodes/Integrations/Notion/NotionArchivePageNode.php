<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionArchivePageNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_archive_page';
    }

    public function name(): string
    {
        return 'Notion: Archive Page';
    }

    public function description(): string
    {
        return 'Moves a page or database item to the trash.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page'],
            'properties' => [
                ...$this->credentialFields(),
                'page' => Field::dynamic('Page', 'notion.pages', 'Pick a page, or paste its link or id.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapePage($this->patch($run, '/pages/'.$this->id($config, 'page', 'The page'), $config, ['archived' => true]));
    }
}
