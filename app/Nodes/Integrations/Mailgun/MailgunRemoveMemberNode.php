<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunRemoveMemberNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_remove_member';
    }

    public function name(): string
    {
        return 'Mailgun: Remove List Member';
    }

    public function description(): string
    {
        return 'Takes a person off a mailing list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'list' => $this->listField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = $this->email($config);
        $response = $this->mailgunResponse($run, $config, 'DELETE', '/v3/lists/'.$this->listAddress($config).'/members/'.rawurlencode($email));

        if ($response->status() === 404) {
            return ['removed' => false, 'found' => false, 'email' => $email];
        }

        $this->assertOk($response);

        return ['removed' => true, 'found' => true, 'email' => $email];
    }
}
