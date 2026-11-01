<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TwilioSendWhatsAppNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_send_whatsapp';
    }

    public function name(): string
    {
        return 'Twilio: Send WhatsApp Message';
    }

    public function description(): string
    {
        return 'Sends a WhatsApp message through Twilio. Outside a 24-hour conversation WhatsApp only allows approved templates (use Content SID).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['to'],
            'properties' => [
                ...$this->credentialFields(),
                'from' => Field::text('From WhatsApp number', 'Your WhatsApp sender, e.g. +14155238886 for the Twilio sandbox. Leave empty if you use a Messaging Service.', '+14155238886'),
                'messaging_service_sid' => Field::advanced(Field::dynamic('Messaging Service', 'twilio.messaging_services', 'Send through a Messaging Service that has a WhatsApp sender.', 'MG...')),
                'to' => Field::text('To', 'The person\'s WhatsApp number, in international format.', '+14155550199'),
                'body' => Field::textarea('Message', 'Free text, within a 24-hour conversation the person started. Up to 1600 characters.'),
                'media_urls' => Field::advanced(Field::list('Media', 'A link to a picture, video or document (one per message).', 'https://example.com/photo.jpg')),
                'content_sid' => Field::advanced(Field::text('Template (Content SID)', 'An approved template\'s SID (it starts with HX), to start a conversation.', 'HX...')),
                'content_variables' => Field::advanced(Field::json('Template variables', 'For a template with placeholders, e.g. {"1": "Ada", "2": "12 Mar"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $to = 'whatsapp:'.preg_replace('/^whatsapp:/', '', $this->phone($config['to'] ?? null, 'To', allowChannel: true));
        [$from, $service] = $this->sender($config, whatsapp: true);
        $media = $this->mediaUrls($config);
        $text = (string) ($config['body'] ?? '');
        $template = trim((string) ($config['content_sid'] ?? ''));

        if ($template === '' && trim($text) === '' && $media === []) {
            throw new InvalidArgumentException('The message needs some text, media or a template.');
        }

        if ($template !== '') {
            $template = $this->sid($config, 'content_sid', 'Content SID', 'HX');
        }

        $variables = $config['content_variables'] ?? null;

        return $this->shapeMessage((array) $this->twilio($run, $config, 'POST', '/Messages.json', [
            'To' => $to,
            'From' => $from,
            'MessagingServiceSid' => $service,
            'Body' => $template === '' && $text !== '' ? $text : null,
            'MediaUrl' => $template === '' ? ($media ?: null) : null,
            'ContentSid' => $template !== '' ? $template : null,
            'ContentVariables' => $template !== '' && $variables !== null && $variables !== '' ? (is_string($variables) ? $variables : json_encode($variables)) : null,
        ]));
    }
}
