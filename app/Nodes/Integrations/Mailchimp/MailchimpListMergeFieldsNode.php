<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpListMergeFieldsNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_list_merge_fields';
    }

    public function name(): string
    {
        return 'Mailchimp: List Merge Fields';
    }

    public function description(): string
    {
        return 'Lists the fields an audience keeps about each person, with their tags (FNAME, LNAME, your own).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->listAll($run, $config, '/lists/'.$this->listId($config).'/merge-fields', 'merge_fields', [], 1000);

        return ['merge_fields' => array_map(fn (array $field): array => ['id' => $field['merge_id'] ?? null, 'tag' => $field['tag'] ?? null, 'name' => $field['name'] ?? null, 'type' => $field['type'] ?? null, 'required' => $field['required'] ?? false, 'default_value' => $field['default_value'] ?? null], $fields), 'count' => count($fields)];
    }
}
