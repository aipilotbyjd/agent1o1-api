<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpCreateCampaignNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_create_campaign';
    }

    public function name(): string
    {
        return 'Mailchimp: Create Campaign';
    }

    public function description(): string
    {
        return 'Creates a regular email campaign (a draft) to an audience, optionally to a tag or segment. Set its content next, then send or schedule it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'subject', 'from_name', 'reply_to'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField(),
                'subject' => Field::text('Subject line', null, 'March news'),
                'preview_text' => Field::advanced(Field::text('Preview text', 'The line shown after the subject in the inbox.')),
                'title' => Field::text('Internal title', 'Only you see it. Defaults to the subject.'),
                'from_name' => Field::text('From name', null, 'Acme'),
                'reply_to' => Field::text('Reply-to email', null, 'hello@acme.com'),
                'segment_id' => Field::advanced(Field::dynamic('Only send to a segment or tag', 'mailchimp.segments', null, 'VIP customers', dependsOn: ['list_id'])),
                'template_id' => Field::advanced(Field::dynamic('Template', 'mailchimp.templates', null, 'Newsletter')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $subject = $this->requiredString($config, 'subject', 'The subject line');
        $segment = $config['segment_id'] ?? null;

        $campaign = (array) $this->mailchimp($run, $config, 'POST', '/campaigns', [
            'type' => 'regular',
            'recipients' => array_filter(['list_id' => $this->listId($config), 'segment_opts' => is_numeric($segment) ? ['saved_segment_id' => (int) $segment] : null], fn (mixed $value): bool => $value !== null),
            'settings' => array_filter([
                'subject_line' => $subject,
                'preview_text' => filled($config['preview_text'] ?? null) ? (string) $config['preview_text'] : null,
                'title' => filled($config['title'] ?? null) ? (string) $config['title'] : $subject,
                'from_name' => $this->requiredString($config, 'from_name', 'The From name'),
                'reply_to' => $this->email($config, 'reply_to'),
                'template_id' => is_numeric($config['template_id'] ?? null) ? (int) $config['template_id'] : null,
            ], fn (mixed $value): bool => $value !== null),
        ]);

        return $this->shapeCampaign($campaign);
    }
}
