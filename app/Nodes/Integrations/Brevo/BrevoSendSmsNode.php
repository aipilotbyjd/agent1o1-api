<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class BrevoSendSmsNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_send_sms';
    }

    public function name(): string
    {
        return 'Brevo: Send Transactional SMS';
    }

    public function description(): string
    {
        return 'Sends one text message through Brevo (needs SMS credits).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['sender', 'recipient', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'sender' => Field::text('From', 'Up to 11 letters, or up to 15 digits.', 'Acme'),
                'recipient' => Field::text('To', 'International format without the +, or with it.', '+14155550199'),
                'content' => Field::textarea('Message'),
                'type' => Field::select('Kind', ['transactional' => 'Transactional (receipts, codes)', 'marketing' => 'Marketing'], 'Marketing messages carry an unsubscribe line and need consent.', 'transactional'),
                'tag' => Field::advanced(Field::text('Tag')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sender = $this->requiredString($config, 'sender', 'The sender');
        $recipient = (string) preg_replace('/[\s().-]+/', '', ltrim(trim((string) ($config['recipient'] ?? '')), '+'));
        $type = (string) ($config['type'] ?? 'transactional');

        if (preg_match('/^(?:[A-Za-z0-9 ]{1,11}|\d{1,15})$/', $sender) !== 1) {
            throw new InvalidArgumentException('The sender is up to 11 letters and digits, or up to 15 digits.');
        }

        if (preg_match('/^[1-9]\d{6,14}$/', $recipient) !== 1) {
            throw new InvalidArgumentException('The recipient must be an international phone number, e.g. +14155550199.');
        }

        $result = (array) $this->brevo($run, $config, 'POST', '/transactionalSMS/sms', [
            'sender' => $sender,
            'recipient' => $recipient,
            'content' => $this->requiredString($config, 'content', 'The message'),
            'type' => in_array($type, ['transactional', 'marketing'], true) ? $type : 'transactional',
            'tag' => filled($config['tag'] ?? null) ? (string) $config['tag'] : null,
        ]);

        return ['sent' => true, 'message_id' => $result['messageId'] ?? null, 'credits_used' => $result['usedCredits'] ?? null, 'credits_left' => $result['remainingCredits'] ?? null, 'recipient' => $recipient];
    }
}
