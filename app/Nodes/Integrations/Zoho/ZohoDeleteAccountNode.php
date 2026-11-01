<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoDeleteAccountNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_delete_account';
    }

    public function name(): string
    {
        return 'Zoho CRM: Delete Account';
    }

    public function description(): string
    {
        return 'Deletes a account. Zoho keeps it in the recycle bin for 60 days.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'record_id' => Field::text('Record id', 'An id from a previous step.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->recordId($config);

        $this->written($this->zoho($run, $config, 'DELETE', '/Accounts', ['ids' => $id]));

        return ['deleted' => true, 'id' => $id, 'module' => 'Accounts'];
    }
}
