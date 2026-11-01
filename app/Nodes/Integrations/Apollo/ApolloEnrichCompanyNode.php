<?php

namespace App\Nodes\Integrations\Apollo;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ApolloEnrichCompanyNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_enrich_company';
    }

    public function name(): string
    {
        return 'Apollo: Enrich Company';
    }

    public function description(): string
    {
        return 'Looks a company up from its domain and returns what it does, its industry, headcount, revenue, location and profiles.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['domain'],
            'properties' => [
                ...$this->credentialFields(),
                'domain' => Field::text('Company domain', 'e.g. acme.com. A web address also works.', 'acme.com'),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'found' => ['title' => 'Found', 'description' => 'True when Apollo knows the company.'],
                'name' => ['title' => 'Name', 'description' => 'The company’s name.'],
                'description' => ['title' => 'Description', 'description' => 'What it does.'],
                'industry' => ['title' => 'Industry', 'description' => 'Its industry.'],
                'employees' => ['title' => 'Employees', 'description' => 'Its estimated headcount.'],
                'revenue' => ['title' => 'Revenue', 'description' => 'Its estimated yearly revenue, printed.'],
                'location' => ['title' => 'Location', 'description' => 'City, state and country.'],
                'founded_year' => ['title' => 'Founded', 'description' => 'The year it was founded.'],
                'website' => ['title' => 'Website', 'description' => 'Its website.'],
                'linkedin_url' => ['title' => 'LinkedIn', 'description' => 'Its LinkedIn page.'],
                'technologies' => ['title' => 'Technologies', 'description' => 'Tools it is known to use.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($config['domain'] ?? '');

        if ($domain === null) {
            throw new InvalidArgumentException('Give a company domain such as acme.com.');
        }

        $organization = (array) ($this->call($run, $config, 'GET', self::API_URL.'/organizations/enrich', query: ['domain' => $domain])['organization'] ?? []);

        return [
            'found' => $organization !== [],
            'name' => $organization['name'] ?? null,
            'description' => $organization['short_description'] ?? null,
            'industry' => $organization['industry'] ?? null,
            'employees' => $organization['estimated_num_employees'] ?? null,
            'revenue' => $organization['organization_revenue_printed'] ?? null,
            'location' => implode(', ', array_filter([$organization['city'] ?? null, $organization['state'] ?? null, $organization['country'] ?? null])) ?: null,
            'founded_year' => $organization['founded_year'] ?? null,
            'website' => $organization['website_url'] ?? null,
            'linkedin_url' => $organization['linkedin_url'] ?? null,
            'technologies' => array_values(array_filter(array_map(fn (mixed $tech): ?string => is_array($tech) ? ($tech['name'] ?? null) : (is_string($tech) ? $tech : null), (array) ($organization['current_technologies'] ?? [])))),
        ];
    }
}
