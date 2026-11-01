<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class BrevoAddContactsToListNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_add_contacts_to_list';
    }

    public function name(): string
    {
        return 'Brevo: Add Contacts to List';
    }

    public function description(): string
    {
        return 'Puts up to 150 existing contacts on a list by email.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'emails'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->listField(),
                'emails' => Field::list('Emails', 'Up to 150.', 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->numericId($config, 'list_id', 'The list');
        $emails = $this->emailList($config);

        $result = (array) $this->brevo($run, $config, 'POST', "/contacts/lists/{$list}/contacts/add", ['emails' => $emails]);

        return ['added' => count((array) ($result['contacts']['success'] ?? $emails)), 'failed' => array_values((array) ($result['contacts']['failure'] ?? [])), 'list_id' => $list];
    }
}
