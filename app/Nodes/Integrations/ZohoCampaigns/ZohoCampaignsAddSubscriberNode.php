<?php

namespace App\Nodes\Integrations\ZohoCampaigns;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ZohoCampaignsAddSubscriberNode extends AbstractZohoCampaignsNode
{
    public function type(): string
    {
        return 'zoho_campaigns_add_subscriber';
    }

    public function name(): string
    {
        return 'Zoho Campaigns: Add Subscriber';
    }

    public function description(): string
    {
        return 'Adds a person to a mailing list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_key', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'list_key' => $this->listField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'extra' => Field::advanced(Field::json('More fields (JSON)', 'By field label, e.g. {"Company Name": "Acme"}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->key($config, 'list_key', 'The mailing list');
        $email = $this->email($config);
        $extra = is_string($config['extra'] ?? null) ? (trim($config['extra']) === '' ? [] : json_decode($config['extra'], true)) : (array) ($config['extra'] ?? []);

        if (! is_array($extra) || ($extra !== [] && array_is_list($extra))) {
            throw new InvalidArgumentException('The more fields must be a JSON object, e.g. {"Company Name": "Acme"}.');
        }

        $this->campaigns($run, $config, 'POST', 'json/listsubscribe', [
            'listkey' => $list,
            'contactinfo' => json_encode(array_filter([...$extra, 'Contact Email' => $email, 'First Name' => $config['first_name'] ?? null, 'Last Name' => $config['last_name'] ?? null], fn (mixed $value): bool => filled($value))),
        ]);

        return ['subscribed' => true, 'email' => $email, 'list_key' => $list];
    }
}
