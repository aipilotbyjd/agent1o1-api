<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MailchimpSendTestEmailNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_send_test_email';
    }

    public function name(): string
    {
        return 'Mailchimp: Send Test Email';
    }

    public function description(): string
    {
        return 'Sends a draft campaign to some addresses to check it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id', 'emails'],
            'properties' => [
                ...$this->credentialFields(),
                'campaign_id' => $this->campaignField(),
                'emails' => Field::list('Send to', 'Up to 5 addresses.', 'me@acme.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->campaignId($config);
        $emails = array_map(fn (string $email): string => $this->email(['e' => $email], 'e'), $this->csv($config['emails'] ?? null));

        if ($emails === [] || count($emails) > 5) {
            throw new InvalidArgumentException('Send a test to between 1 and 5 addresses.');
        }

        $this->mailchimp($run, $config, 'POST', "/campaigns/{$id}/actions/test", ['test_emails' => $emails, 'send_type' => 'html']);

        return ['sent' => true, 'campaign_id' => $id, 'emails' => $emails];
    }
}
