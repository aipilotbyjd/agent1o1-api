<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class KlaviyoSubscribeProfileNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_subscribe_profile';
    }

    public function name(): string
    {
        return 'Klaviyo: Subscribe Profile';
    }

    public function description(): string
    {
        return 'Subscribes a person to marketing email (and optionally text) on a list, creating the profile if needed. Only use it for people who agreed to hear from you. Klaviyo processes it in the background.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->listField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'phone' => Field::advanced(Field::text('Phone', 'International format. Needed to subscribe them to text messages.')),
                'sms' => Field::advanced(Field::boolean('Also subscribe to text messages', 'Only if they agreed to texts.')),
                'source' => Field::advanced(Field::text('Where they signed up', 'Recorded as the consent source.', 'Flows')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->resourceId($config, 'list_id', 'The list');
        $email = $this->email($config);
        $attributes = $this->profileAttributes($config);
        $sms = filter_var($config['sms'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($sms && ! isset($attributes['phone_number'])) {
            throw new InvalidArgumentException('Subscribing to text messages needs a phone number.');
        }

        $this->klaviyo($run, $config, 'POST', '/profile-subscription-bulk-create-jobs/', ['data' => [
            'type' => 'profile-subscription-bulk-create-job',
            'attributes' => [
                'custom_source' => filled($config['source'] ?? null) ? (string) $config['source'] : 'Flows',
                'profiles' => ['data' => [[
                    'type' => 'profile',
                    'attributes' => [
                        'email' => $email,
                        ...(isset($attributes['phone_number']) ? ['phone_number' => $attributes['phone_number']] : []),
                        'subscriptions' => ['email' => ['marketing' => ['consent' => 'SUBSCRIBED']], ...($sms ? ['sms' => ['marketing' => ['consent' => 'SUBSCRIBED']]] : [])],
                    ],
                ]]],
            ],
            'relationships' => ['list' => ['data' => ['type' => 'list', 'id' => $list]]],
        ]]);

        return ['requested' => true, 'list_id' => $list, 'email' => $email, 'sms' => $sms];
    }
}
