<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TwilioSendSmsNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_send_sms';
    }

    public function name(): string
    {
        return 'Twilio: Send SMS';
    }

    public function description(): string
    {
        return 'Sends a text message (or an MMS with pictures, in the US and Canada) from a Twilio number or Messaging Service.';
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
                ...$this->senderFields(),
                'to' => Field::text('To', 'The number to text, in international format.', '+14155550199'),
                'body' => Field::textarea('Message', 'Up to 1600 characters. Needed unless you attach media.'),
                'media_urls' => Field::advanced(Field::list('Media (MMS)', 'Links to pictures or files to send with it, up to 10.', 'https://example.com/photo.jpg')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $to = $this->phone($config['to'] ?? null, 'To');
        [$from, $service] = $this->sender($config);
        $media = $this->mediaUrls($config);
        $text = (string) ($config['body'] ?? '');

        if (trim($text) === '' && $media === []) {
            throw new InvalidArgumentException('The message needs some text or media.');
        }

        if (mb_strlen($text) > 1600) {
            throw new InvalidArgumentException('The message is over Twilio\'s limit of 1600 characters.');
        }

        return $this->shapeMessage((array) $this->twilio($run, $config, 'POST', '/Messages.json', ['To' => $to, 'From' => $from, 'MessagingServiceSid' => $service, 'Body' => $text !== '' ? $text : null, 'MediaUrl' => $media ?: null]));
    }
}
