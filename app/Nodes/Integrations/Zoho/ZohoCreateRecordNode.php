<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoCreateRecordNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_create_record';
    }

    public function name(): string
    {
        return 'Zoho CRM: Create Record (any module)';
    }

    public function description(): string
    {
        return 'Creates a record in any module, standard or custom, from a JSON map of fields.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['module', 'fields'],
            'properties' => [
                ...$this->credentialFields(),
                'module' => Field::dynamic('Module', 'zoho.modules', null, 'Leads', allowCustom: true),
                'fields' => Field::json('Fields (JSON)', 'By API name, e.g. {"Last_Name": "Lovelace", "Company": "Acme"}. A lookup is {"id": "…"}.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $module = $this->apiName((string) ($config['module'] ?? ''));
        $fields = $this->writeFields($config, []);

        if ($fields === []) {
            throw new InvalidArgumentException('Give at least one field.');
        }

        $row = $this->written($this->zoho($run, $config, 'POST', "/{$module}", [], ['data' => [$fields]]));

        return ['id' => data_get($row, 'details.id'), 'module' => $module];
    }
}
