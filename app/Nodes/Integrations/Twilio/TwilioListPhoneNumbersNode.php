<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TwilioListPhoneNumbersNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_list_phone_numbers';
    }

    public function name(): string
    {
        return 'Twilio: List Phone Numbers';
    }

    public function description(): string
    {
        return 'Lists the phone numbers of the account, with what each can do and where it sends messages.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $numbers = $this->listAll($run, $config, '/IncomingPhoneNumbers.json', 'incoming_phone_numbers', [], $this->limit($config, 50));

        return ['numbers' => array_map(fn (array $number): array => [
            'sid' => $number['sid'] ?? null,
            'phone_number' => $number['phone_number'] ?? null,
            'friendly_name' => $number['friendly_name'] ?? null,
            'can_sms' => data_get($number, 'capabilities.sms', false),
            'can_mms' => data_get($number, 'capabilities.mms', false),
            'can_voice' => data_get($number, 'capabilities.voice', false),
            'sms_url' => ($number['sms_url'] ?? '') !== '' ? $number['sms_url'] : null,
            'voice_url' => ($number['voice_url'] ?? '') !== '' ? $number['voice_url'] : null,
            'date_created' => $this->date($number['date_created'] ?? null),
        ], $numbers), 'count' => count($numbers)];
    }
}
