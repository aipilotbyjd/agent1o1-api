<?php

namespace App\Nodes\Integrations\ZohoMail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class ZohoMailSendEmailNode extends AbstractZohoMailNode
{
    public function type(): string
    {
        return 'zoho_mail_send_email';
    }

    public function name(): string
    {
        return 'Zoho Mail: Send Email';
    }

    public function description(): string
    {
        return 'Sends an email from the connected account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['to', 'subject', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'to' => Field::emails('To'),
                'subject' => Field::text('Subject'),
                'content' => Field::textarea('Message'),
                'html' => Field::advanced(Field::boolean('The message is HTML', null, false)),
                'cc' => Field::advanced(Field::emails('Cc')),
                'bcc' => Field::advanced(Field::emails('Bcc')),
                'from' => Field::advanced(Field::text('From', 'An address of the account. Empty uses the main address.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $to = $this->addresses($config, 'to', true);
        $from = filled($config['from'] ?? null) ? $this->addresses($config, 'from', true)[0] : null;

        if ($from === null) {
            $id = $this->accountId($run, $config);
            $mine = collect((array) ($this->mail($run, $config, 'GET', 'accounts')['data'] ?? []))->first(fn (mixed $account): bool => is_array($account) && (string) ($account['accountId'] ?? '') === $id);
            $from = $mine['primaryEmailAddress'] ?? $mine['mailboxAddress'] ?? throw new RuntimeException('Zoho Mail did not show the address to send from. Fill in From.');
        }

        $sent = $this->account($run, $config, 'POST', '/messages', [], array_filter([
            'fromAddress' => $from,
            'toAddress' => implode(',', $to),
            'ccAddress' => $this->addresses($config, 'cc') ? implode(',', $this->addresses($config, 'cc')) : null,
            'bccAddress' => $this->addresses($config, 'bcc') ? implode(',', $this->addresses($config, 'bcc')) : null,
            'subject' => $this->requiredString($config, 'subject', 'The subject'),
            'content' => $this->requiredString($config, 'content', 'The message'),
            'mailFormat' => ($config['html'] ?? false) ? 'html' : 'plaintext',
        ], fn (mixed $value): bool => $value !== null));

        return ['sent' => true, 'from' => $from, 'to' => $to, 'id' => isset($sent['data']['messageId']) ? (string) $sent['data']['messageId'] : null];
    }
}
