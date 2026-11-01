<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionGetPageNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_get_page';
    }

    public function name(): string
    {
        return 'Notion: Get Page';
    }

    public function description(): string
    {
        return 'Gets a page with its properties.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->shapePage($this->get($run, '/pages/'.$this->id($config, 'page', 'The page'), $config));
    }
}
