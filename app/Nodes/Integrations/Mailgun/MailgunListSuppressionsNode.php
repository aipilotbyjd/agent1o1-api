<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunListSuppressionsNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_list_suppressions';
    }

    public function name(): string
    {
        return 'Mailgun: List Suppressions';
    }

    public function description(): string
    {
        return 'Lists the addresses Mailgun will not send to: bounces, unsubscribes or spam complaints.';
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
                'kind' => Field::select('List', ['bounces' => 'Bounces', 'unsubscribes' => 'Unsubscribes', 'complaints' => 'Spam complaints'], null, 'bounces'),
                'limit' => Field::integer('Max results', null, 100, 1, 10000),
                'domain' => Field::advanced(Field::text('Domain', 'Another domain of the account. Empty uses the connection\'s.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($run, $config);
        $kind = in_array($config['kind'] ?? 'bounces', ['bounces', 'unsubscribes', 'complaints'], true) ? ($config['kind'] ?? 'bounces') : 'bounces';
        $rows = $this->listAll($run, $config, "/v3/{$domain}/{$kind}", [], $this->limit($config, 100, 10000), 1000);

        return ['kind' => $kind, 'suppressions' => array_map(fn (array $row): array => ['email' => $row['address'] ?? null, 'code' => $row['code'] ?? null, 'reason' => $row['error'] ?? null, 'tags' => $row['tags'] ?? null, 'created_at' => $row['created_at'] ?? null], $rows), 'count' => count($rows)];
    }
}
