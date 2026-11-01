<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class BrevoCreateListNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_create_list';
    }

    public function name(): string
    {
        return 'Brevo: Create List';
    }

    public function description(): string
    {
        return 'Creates a contact list in a folder.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'folder_id'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Newsletter'),
                'folder_id' => Field::dynamic('Folder', 'brevo.folders', 'The folder to create it in.', 'Your lists', type: 'integer'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = $this->requiredString($config, 'name', 'The name');
        $created = (array) $this->brevo($run, $config, 'POST', '/contacts/lists', ['name' => $name, 'folderId' => $this->numericId($config, 'folder_id', 'The folder')]);

        return ['id' => $created['id'] ?? null, 'name' => $name];
    }
}
