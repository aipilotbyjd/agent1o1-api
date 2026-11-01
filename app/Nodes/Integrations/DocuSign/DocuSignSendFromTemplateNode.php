<?php

namespace App\Nodes\Integrations\DocuSign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DocuSignSendFromTemplateNode extends AbstractDocuSignNode
{
    public function type(): string
    {
        return 'docusign_send_from_template';
    }

    public function name(): string
    {
        return 'DocuSign: Send Template for Signature';
    }

    public function description(): string
    {
        return 'Sends one of your DocuSign templates to the people who must sign it, filling each role with a name and email.';
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
                ...$this->accountFields(),
                'template_id' => Field::text('Template ID', 'From List Templates, or DocuSign > Templates.', 'a1b2c3d4-0000-0000-0000-000000000000'),
                'signers' => Field::json('Who signs', 'One entry per role in the template: [{"role": "Client", "name": "Ada Lovelace", "email": "ada@example.com"}]. The role must match the template’s role name.', 'array'),
                'subject' => Field::text('Email subject', 'Leave empty to use the template’s.'),
                'message' => Field::textarea('Email message', 'Leave empty to use the template’s.'),
                'send' => Field::boolean('Send now', 'Off saves it as a draft in DocuSign to review before sending.', true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $templateId = trim((string) ($config['template_id'] ?? ''));

        if ($templateId === '') {
            throw new InvalidArgumentException('Give the template to send.');
        }

        $signers = $this->signers($config);

        foreach ($signers as $index => $signer) {
            if ($signer['role'] === '') {
                throw new InvalidArgumentException('Signer '.($index + 1).' needs a role, matching a role in the template.');
            }
        }

        $envelope = $this->call($run, $config, 'POST', $this->root($run, $config).'/envelopes', array_filter([
            'templateId' => $templateId,
            'templateRoles' => array_map(fn (array $signer): array => ['roleName' => $signer['role'], 'name' => $signer['name'], 'email' => $signer['email'], 'routingOrder' => (string) $signer['order']], $signers),
            'emailSubject' => filled($config['subject'] ?? null) ? mb_substr(trim((string) $config['subject']), 0, 100) : null,
            'emailBlurb' => filled($config['message'] ?? null) ? mb_substr(trim((string) $config['message']), 0, 10000) : null,
            'status' => (bool) ($config['send'] ?? true) ? 'sent' : 'created',
        ], fn (mixed $value): bool => $value !== null));

        return ['envelope_id' => $envelope['envelopeId'] ?? null, 'status' => $envelope['status'] ?? null, 'sent_to' => array_column($signers, 'email')];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'envelope_id' => ['title' => 'Envelope ID', 'description' => 'DocuSign’s ID for what was sent. Use it to check status or download the signed copy.'],
                'status' => ['title' => 'Status', 'description' => 'sent, or created when saved as a draft.'],
                'sent_to' => ['title' => 'Sent to', 'description' => 'The signers’ email addresses.'],
            ],
        ];
    }
}
