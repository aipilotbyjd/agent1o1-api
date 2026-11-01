<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitListFormsNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_list_forms';
    }

    public function name(): string
    {
        return 'Kit: List Forms';
    }

    public function description(): string
    {
        return 'Lists the account\'s forms and landing pages.';
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
                'type' => Field::select('Kind', ['all' => 'All', 'embed' => 'Embedded forms', 'hosted' => 'Landing pages'], null, 'all'),
                'status' => Field::select('Status', ['active' => 'Active', 'archived' => 'Archived', 'trashed' => 'Trashed', 'all' => 'All'], null, 'active'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $type = (string) ($config['type'] ?? 'all');
        $forms = $this->listAll($run, $config, '/forms', 'forms', ['type' => $type !== 'all' && $type !== '' ? $type : null, 'status' => $config['status'] ?? 'active'], 1000);

        return ['forms' => array_map(fn (array $form): array => ['id' => $form['id'] ?? null, 'name' => $form['name'] ?? null, 'type' => $form['type'] ?? null, 'format' => $form['format'] ?? null, 'embed_url' => $form['embed_url'] ?? null, 'archived' => $form['archived'] ?? false, 'created_at' => $form['created_at'] ?? null], $forms), 'count' => count($forms)];
    }
}
