<?php

namespace App\Nodes\Integrations\DropboxSign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DropboxSignRemindNode extends AbstractDropboxSignNode
{
    public function type(): string
    {
        return 'dropbox_sign_remind';
    }

    public function name(): string
    {
        return 'Dropbox Sign: Remind Signer';
    }

    public function description(): string
    {
        return 'Sends a signer a reminder to sign. Dropbox Sign allows one reminder per signer every so often.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['signature_request_id', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'signature_request_id' => Field::text('Request ID', null, 'fa5c8a0b0f492d768749333ad6fcc214c111e967'),
                'email' => Field::text('Signer’s email', 'Who to remind. They must be a signer on the request.', 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->requestId($config);
        $email = strtolower(trim((string) ($config['email'] ?? '')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("“{$email}” is not an email address.");
        }

        $this->call($run, $config, 'POST', self::API_URL."/signature_request/remind/{$id}", ['email_address' => $email]);

        return ['signature_request_id' => $id, 'reminded' => $email];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'signature_request_id' => ['title' => 'Request ID', 'description' => 'The request.'],
                'reminded' => ['title' => 'Reminded', 'description' => 'The email address reminded.'],
            ],
        ];
    }
}
