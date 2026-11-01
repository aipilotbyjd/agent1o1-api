<?php

namespace App\Nodes\Integrations\DropboxSign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DropboxSignSendFromTemplateNode extends AbstractDropboxSignNode
{
    public function type(): string
    {
        return 'dropbox_sign_send_from_template';
    }

    public function name(): string
    {
        return 'Dropbox Sign: Send Template for Signature';
    }

    public function description(): string
    {
        return 'Sends one of your Dropbox Sign templates, with its signature fields already placed, to the people filling each role.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['template_id', 'signers'],
            'properties' => [
                ...$this->credentialFields(),
                'template_id' => Field::text('Template ID', 'From List Templates, or Dropbox Sign > Templates.', 'c26b8a16784a872da37ea946b9ddec7c1e11dff6'),
                'signers' => Field::json('Who signs', 'One entry per role in the template: [{"role": "Client", "name": "Ada Lovelace", "email": "ada@example.com"}]. The role must match the template’s role name.', 'array'),
                'subject' => Field::text('Email subject', 'Leave empty to use the template’s.'),
                'message' => Field::textarea('Email message', 'Leave empty to use the template’s.'),
                'custom_fields' => Field::keyValue('Fill in fields', 'Optional. Values for the template’s merge fields: the field name on the left, what to put in it on the right.'),
                ...$this->testModeField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $templateId = trim((string) ($config['template_id'] ?? ''));

        if (preg_match('/^[0-9a-f]{20,64}$/i', $templateId) !== 1) {
            throw new InvalidArgumentException('That is not a template ID. Use the ID from List Templates.');
        }

        $signers = $this->signers($config, needRole: true);
        $custom = is_array($config['custom_fields'] ?? null) ? $config['custom_fields'] : [];

        $body = $this->call($run, $config, 'POST', self::API_URL.'/signature_request/send_with_template', array_filter([
            'template_ids' => [$templateId],
            'signers' => array_map(fn (array $signer): array => ['role' => $signer['role'], 'name' => $signer['name'], 'email_address' => $signer['email_address']], $signers),
            'subject' => filled($config['subject'] ?? null) ? mb_substr(trim((string) $config['subject']), 0, 255) : null,
            'message' => filled($config['message'] ?? null) ? mb_substr(trim((string) $config['message']), 0, 5000) : null,
            'custom_fields' => $custom === [] ? null : array_map(fn (mixed $name, mixed $value): array => ['name' => (string) $name, 'value' => (string) $value], array_keys($custom), array_values($custom)),
            'test_mode' => (bool) ($config['test_mode'] ?? false) ? 1 : 0,
        ], fn (mixed $value): bool => $value !== null));

        return [...$this->shapeRequest((array) ($body['signature_request'] ?? [])), 'sent_to' => array_column($signers, 'email_address')];
    }

    public function outputSchema(): array
    {
        return (new DropboxSignSendRequestNode)->outputSchema();
    }
}
