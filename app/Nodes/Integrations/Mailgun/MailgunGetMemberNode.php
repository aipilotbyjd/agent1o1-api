<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunGetMemberNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_get_member';
    }

    public function name(): string
    {
        return 'Mailgun: Get List Member';
    }

    public function description(): string
    {
        return 'Reads a person on a mailing list. Says whether they are on it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $response = $this->mailgunResponse($run, $config, 'GET', '/v3/lists/'.$this->listAddress($config).'/members/'.rawurlencode($email));

        if ($response->status() === 404) {
            return ['found' => false, 'email' => $email];
        }

        $this->assertOk($response);

        return [...$this->shapeMember((array) $response->json('member')), 'found' => true];
    }
}
