<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TypeformListThemesNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_list_themes';
    }

    public function name(): string
    {
        return 'Typeform: List Themes';
    }

    public function description(): string
    {
        return 'Lists the themes of the account (colours and fonts) to apply to a form.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 50, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = (array) $this->typeform($run, $config, 'GET', '/themes', query: ['page_size' => $this->limit($config, 50, 200)]);
        $items = array_values(array_filter((array) ($result['items'] ?? []), 'is_array'));

        return ['themes' => array_map(fn (array $theme): array => ['id' => $theme['id'] ?? null, 'name' => $theme['name'] ?? null, 'font' => $theme['font'] ?? null, 'visibility' => $theme['visibility'] ?? null], $items), 'count' => count($items)];
    }
}
