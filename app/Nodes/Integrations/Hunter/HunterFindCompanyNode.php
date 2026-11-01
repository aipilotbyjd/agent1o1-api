<?php

namespace App\Nodes\Integrations\Hunter;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class HunterFindCompanyNode extends AbstractHunterNode
{
    public function type(): string
    {
        return 'hunter_find_company';
    }

    public function name(): string
    {
        return 'Hunter: Find Company';
    }

    public function description(): string
    {
        return 'Looks up a company from its domain: name, what it does, industry, size, location and social profiles.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['domain'],
            'properties' => [
                ...$this->credentialFields(),
                'domain' => Field::text('Company domain', 'e.g. acme.com.', 'acme.com'),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'found' => ['title' => 'Found', 'description' => 'True when Hunter knows the company.'],
                'name' => ['title' => 'Name', 'description' => 'The company’s name.'],
                'description' => ['title' => 'Description', 'description' => 'What the company does.'],
                'industry' => ['title' => 'Industry', 'description' => 'Its industry.'],
                'size' => ['title' => 'Size', 'description' => 'Its headcount range.'],
                'country' => ['title' => 'Country', 'description' => 'Where it is based.'],
                'city' => ['title' => 'City', 'description' => 'The city.'],
                'linkedin' => ['title' => 'LinkedIn', 'description' => 'Its LinkedIn page.'],
                'domain' => ['title' => 'Domain', 'description' => 'The domain looked up.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($config);
        $data = $this->data($this->call($run, $config, 'GET', self::API_URL.'/companies/find', query: ['domain' => $domain]));

        return [
            'found' => filled($data['name'] ?? null),
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'industry' => $data['category']['industry'] ?? $data['category']['sector'] ?? null,
            'size' => $data['metrics']['employeesRange'] ?? $data['metrics']['employees'] ?? null,
            'country' => $data['geo']['country'] ?? null,
            'city' => $data['geo']['city'] ?? null,
            'linkedin' => filled($data['linkedin']['handle'] ?? null) ? 'https://www.linkedin.com/'.ltrim((string) $data['linkedin']['handle'], '/') : null,
            'domain' => $domain,
        ];
    }
}
