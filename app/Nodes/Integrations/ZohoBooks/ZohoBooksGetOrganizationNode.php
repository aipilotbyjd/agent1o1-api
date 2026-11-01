<?php

namespace App\Nodes\Integrations\ZohoBooks;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZohoBooksGetOrganizationNode extends AbstractZohoBooksNode
{
    public function type(): string
    {
        return 'zoho_books_get_organization';
    }

    public function name(): string
    {
        return 'Zoho Books: Get Organization';
    }

    public function description(): string
    {
        return 'Reads the connected organization and checks the connection works.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $data = $this->resolveCredentialData($run, $config);
        $organizations = (array) ($this->books($run, $config, 'GET', '/organizations')['organizations'] ?? []);
        $mine = collect($organizations)->first(fn (mixed $organization): bool => is_array($organization) && (string) ($organization['organization_id'] ?? '') === trim((string) ($data['organization_id'] ?? '')));

        return $mine === null
            ? ['found' => false, 'organization_count' => count($organizations)]
            : ['found' => true, 'id' => (string) $mine['organization_id'], 'name' => $mine['name'] ?? null, 'currency' => $mine['currency_code'] ?? null, 'country' => $mine['country_code'] ?? null, 'time_zone' => $mine['time_zone'] ?? null];
    }
}
