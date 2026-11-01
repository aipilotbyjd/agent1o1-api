<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionRestorePageNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_restore_page';
    }

    public function name(): string
    {
        return 'Notion: Restore Page';
    }

    public function description(): string
    {
        return 'Brings a page or database item back from the trash.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page'],
            'properties' => [
                ...$this->credentialFields(),
                'page' => Field::text('Page', 'The link or id of the archived page.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapePage($this->patch($run, '/pages/'.$this->id($config, 'page', 'The page'), $config, ['archived' => false]));
    }
}
