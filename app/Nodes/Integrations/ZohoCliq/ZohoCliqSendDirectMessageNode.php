<?php

namespace App\Nodes\Integrations\ZohoCliq;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoCliqSendDirectMessageNode extends AbstractZohoCliqNode
{
    public function type(): string
    {
        return 'zoho_cliq_send_direct_message';
    }

    public function name(): string
    {
        return 'Zoho Cliq: Send Direct Message';
    }

    public function description(): string
    {
        return 'Sends a private message to a person by their email address.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@acme.com'),
                'text' => Field::textarea('Message'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = strtolower(trim((string) ($config['email'] ?? '')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException($email === '' ? 'The email address is required.' : "\"{$email}\" is not an email address.");
        }

        $sent = $this->cliq($run, $config, 'POST', '/buddies/'.rawurlencode($email).'/message', [], ['text' => $this->requiredString($config, 'text', 'The message')]);

        return ['sent' => true, 'email' => $email, 'id' => isset($sent['message_id']) ? (string) $sent['message_id'] : (isset($sent['id']) ? (string) $sent['id'] : null)];
    }
}
