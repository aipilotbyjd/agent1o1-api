<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoUpsertRecordNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_upsert_record';
    }

    public function name(): string
    {
        return 'Zoho CRM: Create or Update Record';
    }

    public function description(): string
    {
        return 'Updates the record that matches on the chosen fields (an email, a name), or creates it. Safe to run twice.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['module', 'duplicate_fields', 'fields'],
            'properties' => [
                ...$this->credentialFields(),
                'module' => Field::dynamic('Module', 'zoho.modules', null, 'Contacts', allowCustom: true),
                'duplicate_fields' => Field::list('Match on', 'API names, one per row, e.g. Email. They must be in Fields.', 'Email'),
                'fields' => Field::json('Fields (JSON)', 'By API name, e.g. {"Last_Name": "Lovelace", "Email": "ada@example.com"}.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $module = $this->apiName((string) ($config['module'] ?? ''));
        $fields = $this->writeFields($config, []);
        $match = array_values(array_unique(array_map(fn (mixed $name): string => $this->apiName((string) $name, 'A match field'), array_filter((array) ($config['duplicate_fields'] ?? []), fn (mixed $name): bool => trim((string) $name) !== ''))));

        if ($match === []) {
            throw new InvalidArgumentException('Give at least one field to match on.');
        }

        foreach ($match as $name) {
            if (! array_key_exists($name, $fields)) {
                throw new InvalidArgumentException("The match field \"{$name}\" must also be in Fields.");
            }
        }

        $row = $this->written($this->zoho($run, $config, 'POST', "/{$module}/upsert", [], ['data' => [$fields], 'duplicate_check_fields' => $match]));

        return ['id' => data_get($row, 'details.id'), 'module' => $module, 'created' => ($row['action'] ?? 'insert') === 'insert'];
    }
}
