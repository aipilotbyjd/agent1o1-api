<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class BrevoRemoveContactsFromListNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_remove_contacts_from_list';
    }

    public function name(): string
    {
        return 'Brevo: Remove Contacts from List';
    }

    public function description(): string
    {
        return 'Takes up to 150 contacts off a list by email (they stay in Brevo).';
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

        $this->brevo($run, $config, 'POST', "/contacts/lists/{$list}/contacts/remove", ['emails' => $emails]);

        return ['removed' => count($emails), 'list_id' => $list];
    }
}
