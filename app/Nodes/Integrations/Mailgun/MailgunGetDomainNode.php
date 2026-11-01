<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunGetDomainNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_get_domain';
    }

    public function name(): string
    {
        return 'Mailgun: Get Domain';
    }

    public function description(): string
    {
        return 'Reads a sending domain with the DNS records it needs and whether each is valid.';
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
                'domain' => Field::advanced(Field::text('Domain', 'Another domain of the account. Empty uses the connection\'s.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = (array) $this->mailgun($run, $config, 'GET', '/v4/domains/'.$this->domain($run, $config));
        $domain = (array) ($found['domain'] ?? []);
        $records = fn (string $key): array => array_values(array_map(fn (array $record): array => ['type' => $record['record_type'] ?? null, 'name' => $record['name'] ?? null, 'value' => $record['value'] ?? null, 'valid' => $record['valid'] ?? null], array_filter((array) ($found[$key] ?? []), 'is_array')));

        return ['name' => $domain['name'] ?? null, 'state' => $domain['state'] ?? null, 'type' => $domain['type'] ?? null, 'created_at' => $domain['created_at'] ?? null, 'sending_records' => $records('sending_dns_records'), 'receiving_records' => $records('receiving_dns_records')];
    }
}
