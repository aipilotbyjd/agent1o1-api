<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpCreateAudienceNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_create_audience';
    }

    public function name(): string
    {
        return 'Mailchimp: Create Audience';
    }

    public function description(): string
    {
        return 'Creates an audience. Mailchimp requires a postal address and a reminder of why people are on it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'company', 'address', 'city', 'state', 'zip', 'country', 'from_name', 'from_email', 'permission_reminder'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Newsletter'),
                'permission_reminder' => Field::text('Permission reminder', 'Why people are on the list, shown in every email.', 'You signed up on our website.'),
                'from_name' => Field::text('Default From name', null, 'Acme'),
                'from_email' => Field::text('Default From email', null, 'hello@acme.com'),
                'subject' => Field::text('Default subject', null, 'News from Acme'),
                'company' => Field::text('Company'),
                'address' => Field::text('Address'),
                'city' => Field::text('City'),
                'state' => Field::text('State or region'),
                'zip' => Field::text('Postal code'),
                'country' => Field::text('Country', 'Two-letter code.', 'US'),
                'double_opt_in' => Field::boolean('Ask new subscribers to confirm', 'Double opt-in.'),
                'language' => Field::advanced(Field::text('Language', null, 'en')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = (array) $this->mailchimp($run, $config, 'POST', '/lists', [
            'name' => $this->requiredString($config, 'name', 'The name'),
            'permission_reminder' => $this->requiredString($config, 'permission_reminder', 'The permission reminder'),
            'email_type_option' => false,
            'double_optin' => filter_var($config['double_opt_in'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'contact' => [
                'company' => $this->requiredString($config, 'company', 'The company'),
                'address1' => $this->requiredString($config, 'address', 'The address'),
                'city' => $this->requiredString($config, 'city', 'The city'),
                'state' => $this->requiredString($config, 'state', 'The state'),
                'zip' => $this->requiredString($config, 'zip', 'The postal code'),
                'country' => strtoupper($this->requiredString($config, 'country', 'The country')),
            ],
            'campaign_defaults' => [
                'from_name' => $this->requiredString($config, 'from_name', 'The From name'),
                'from_email' => $this->email(['e' => $config['from_email'] ?? ''], 'e'),
                'subject' => (string) ($config['subject'] ?? ''),
                'language' => (string) ($config['language'] ?? 'en'),
            ],
        ]);

        return $this->shapeList($list);
    }
}
