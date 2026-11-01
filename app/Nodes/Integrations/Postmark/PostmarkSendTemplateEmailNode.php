<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;
use stdClass;

class PostmarkSendTemplateEmailNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_send_template_email';
    }

    public function name(): string
    {
        return 'Postmark: Send Email from Template';
    }

    public function description(): string
    {
        return 'Sends a transactional email filled from a Postmark template (by id or alias) and the values you give.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['sender_email', 'to', 'template'],
            'properties' => [
                ...$this->credentialFields(),
                'sender_email' => Field::text('From address', 'A confirmed sender signature or domain.', 'hello@acme.com'),
                'sender_name' => Field::advanced(Field::text('From name')),

                'to' => Field::emails('To', 'Up to 50 addresses.'),
                'cc' => Field::advanced(Field::emails('Cc')),
                'bcc' => Field::advanced(Field::emails('Bcc')),
                'reply_to' => Field::advanced(Field::text('Reply-to address')),
                'tag' => Field::advanced(Field::text('Tag', 'One label to group this mail in the stats.')),
                'track_opens' => Field::advanced(Field::boolean('Track opens', null, false)),
                'track_links' => Field::advanced(Field::select('Track links', ['None' => 'No', 'HtmlAndText' => 'HTML and text', 'HtmlOnly' => 'HTML only', 'TextOnly' => 'Text only'], null, 'None')),
                'metadata' => Field::advanced(Field::json('Metadata (JSON)', 'Your own values, returned with events, e.g. {"order": "1001"}.')),
                'headers' => Field::advanced(Field::json('Headers (JSON)', 'Extra headers as name and value, e.g. {"X-Entity-Ref-ID": "123"}.')),
                'stream' => $this->streamField(),

                'template' => Field::dynamic('Template', 'postmark.templates', 'Pick a template, or type its id or alias.', 'welcome', allowCustom: true),
                'template_model' => Field::json('Template values (JSON)', 'What the template uses, e.g. {"name": "Ada", "order": {"id": 1001}}.'),
                'inline_css' => Field::advanced(Field::boolean('Inline the CSS', 'Move the template\'s styles into the HTML.', true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $to = $this->emails($config, 'to');

        if ($to === [] || count($to) > 50) {
            throw new InvalidArgumentException('Give between 1 and 50 recipients.');
        }

        $headers = [];

        foreach ((array) ($this->decoded($config['headers'] ?? null, 'The headers') ?? []) as $name => $value) {
            $headers[] = ['Name' => $this->oneLine($name), 'Value' => $this->oneLine($value)];
        }

        $cc = $this->emails($config, 'cc');
        $bcc = $this->emails($config, 'bcc');
        $replyTo = filled($config['reply_to'] ?? null) ? $this->email($config, 'reply_to') : null;
        $envelope = [
            'From' => $this->sender($config),
            'To' => implode(',', $to),
            'Cc' => $cc === [] ? null : implode(',', $cc),
            'Bcc' => $bcc === [] ? null : implode(',', $bcc),
            'ReplyTo' => $replyTo,
            'Tag' => filled($config['tag'] ?? null) ? $this->oneLine($config['tag']) : null,
            'TrackOpens' => ($config['track_opens'] ?? false) ? true : null,
            'TrackLinks' => in_array($config['track_links'] ?? 'None', ['HtmlAndText', 'HtmlOnly', 'TextOnly'], true) ? $config['track_links'] : null,
            'Metadata' => $this->decoded($config['metadata'] ?? null, 'The metadata'),
            'Headers' => $headers === [] ? null : $headers,
            'MessageStream' => $this->stream($config),
        ];

        $template = $this->requiredString($config, 'template', 'The template');

        $sent = (array) $this->postmark($run, $config, 'POST', '/email/withTemplate', [
            ...$envelope,
            ctype_digit($template) ? 'TemplateId' : 'TemplateAlias' => ctype_digit($template) ? (int) $template : $template,
            'TemplateModel' => $this->decoded($config['template_model'] ?? null, 'The template values') ?? new stdClass,
            'InlineCss' => (bool) ($config['inline_css'] ?? true),
        ]);

        return ['sent' => true, 'message_id' => $sent['MessageID'] ?? null, 'to' => $to, 'submitted_at' => $sent['SubmittedAt'] ?? null];
    }
}
