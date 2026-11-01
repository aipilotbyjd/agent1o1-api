<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TwilioLookupPhoneNumberNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_lookup_phone_number';
    }

    public function name(): string
    {
        return 'Twilio: Look Up Phone Number';
    }

    public function description(): string
    {
        return 'Checks that a number is real and formats it, and optionally tells its line type (mobile, landline, VoIP), carrier and caller name (extra cost).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['phone_number'],
            'properties' => [
                ...$this->credentialFields(),
                'phone_number' => Field::text('Phone number', 'In international format, or in the country below.', '+14155550199'),
                'country_code' => Field::advanced(Field::text('Country', 'Two-letter country code, for a number written without +.', 'US')),
                'include_line_type' => Field::boolean('Include line type and carrier', 'Mobile, landline or VoIP. Billed per lookup.'),
                'include_caller_name' => Field::boolean('Include caller name', 'The name the number is registered to (US). Billed per lookup.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $number = trim($this->requiredString($config, 'phone_number', 'The phone number'));
        $fields = array_filter([filter_var($config['include_line_type'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'line_type_intelligence' : null, filter_var($config['include_caller_name'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'caller_name' : null]);
        $country = strtoupper(trim((string) ($config['country_code'] ?? '')));

        if (preg_match('/^[+\d\s().-]{5,25}$/', $number) !== 1) {
            throw new InvalidArgumentException("\"{$number}\" doesn't look like a phone number.");
        }

        $result = (array) $this->twilio($run, $config, 'GET', '/v2/PhoneNumbers/'.rawurlencode(preg_replace('/[\s().-]+/', '', $number)), ['Fields' => $fields === [] ? null : implode(',', $fields), 'CountryCode' => $country !== '' ? $country : null], 'lookups');

        return [
            'phone_number' => $result['phone_number'] ?? null,
            'national_format' => $result['national_format'] ?? null,
            'country_code' => $result['country_code'] ?? null,
            'calling_country_code' => $result['calling_country_code'] ?? null,
            'valid' => $result['valid'] ?? false,
            'validation_errors' => array_values((array) ($result['validation_errors'] ?? [])),
            'line_type' => data_get($result, 'line_type_intelligence.type'),
            'carrier' => data_get($result, 'line_type_intelligence.carrier_name'),
            'caller_name' => data_get($result, 'caller_name.caller_name'),
            'caller_type' => data_get($result, 'caller_name.caller_type'),
        ];
    }
}
