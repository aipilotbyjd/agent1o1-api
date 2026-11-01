<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TwilioStartVerificationNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_start_verification';
    }

    public function name(): string
    {
        return 'Twilio: Send Verification Code';
    }

    public function description(): string
    {
        return 'Sends a one-time code by text, call, WhatsApp or email, to check that a person owns a number or address.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['service_sid', 'to'],
            'properties' => [
                ...$this->credentialFields(),
                'service_sid' => Field::dynamic('Verify service', 'twilio.verify_services', 'The Verify service to use. Create one in the Twilio Console under Verify > Services.', 'VA...'),
                'to' => Field::text('To', 'A phone number in international format, or an email address for the email channel.', '+14155550199'),
                'channel' => Field::select('Send by', ['sms' => 'Text message', 'call' => 'Phone call', 'whatsapp' => 'WhatsApp', 'email' => 'Email'], null, 'sms'),
                'locale' => Field::advanced(Field::text('Language', 'The language of the message, e.g. en, fr, de.', 'en')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $service = $this->sid($config, 'service_sid', 'Verify service SID', 'VA');
        $channel = (string) ($config['channel'] ?? 'sms');

        if (! in_array($channel, ['sms', 'call', 'whatsapp', 'email'], true)) {
            throw new InvalidArgumentException("\"{$channel}\" is not a way to send a code. Use sms, call, whatsapp or email.");
        }

        $to = $this->verifyTarget($config, $channel);
        $verification = (array) $this->twilio($run, $config, 'POST', "/v2/Services/{$service}/Verifications", ['To' => $to, 'Channel' => $channel, 'Locale' => filled($config['locale'] ?? null) ? (string) $config['locale'] : null], 'verify');

        return ['sid' => $verification['sid'] ?? null, 'status' => $verification['status'] ?? null, 'channel' => $verification['channel'] ?? $channel, 'to' => $verification['to'] ?? $to, 'service_sid' => $service, 'date_created' => $this->date($verification['date_created'] ?? null)];
    }

    /**
     * The number or address a code goes to, checked for its channel.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function verifyTarget(array $config, string $channel): string
    {
        if ($channel === 'email') {
            $email = trim((string) ($config['to'] ?? ''));

            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : throw new InvalidArgumentException("\"{$email}\" is not an email address.");
        }

        return $this->phone($config['to'] ?? null, 'To');
    }
}
