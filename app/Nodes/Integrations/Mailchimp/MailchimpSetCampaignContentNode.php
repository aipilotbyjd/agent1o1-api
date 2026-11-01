<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpSetCampaignContentNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_set_campaign_content';
    }

    public function name(): string
    {
        return 'Mailchimp: Set Campaign Content';
    }

    public function description(): string
    {
        return 'Sets the body of a draft campaign from HTML (or plain text).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id', 'html'],
            'properties' => [
                ...$this->credentialFields(),
                'campaign_id' => $this->campaignField('The draft to fill in.'),
                'html' => Field::textarea('HTML', 'The email body. Include *|UNSUB|* so people can unsubscribe.'),
                'plain_text' => Field::advanced(Field::textarea('Plain-text version', 'Optional. Mailchimp makes one from the HTML if empty.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->campaignId($config);
        $html = $this->requiredString($config, 'html', 'The HTML');

        $this->mailchimp($run, $config, 'PUT', "/campaigns/{$id}/content", ['html' => $html, 'plain_text' => filled($config['plain_text'] ?? null) ? (string) $config['plain_text'] : null]);

        return ['campaign_id' => $id, 'content_set' => true];
    }
}
