<?php

namespace App\Nodes\Integrations\Apollo;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ApolloEnrichPersonNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_enrich_person';
    }

    public function name(): string
    {
        return 'Apollo: Enrich Person';
    }

    public function description(): string
    {
        return 'Looks a person up from their email, LinkedIn page or name and company: title, employer, location and profile.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', 'The best way to match someone.', 'ada@example.com'),
                'linkedin_url' => Field::text('LinkedIn page', null, 'https://www.linkedin.com/in/ada'),
                'first_name' => Field::text('First name', 'Use with a last name and a company.'),
                'last_name' => Field::text('Last name'),
                'company' => Field::text('Company name or domain', 'Helps match a name.', 'acme.com'),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'found' => ['title' => 'Found', 'description' => 'True when Apollo matched a person.'],
                'result' => ['title' => 'Branch', 'description' => 'found or none, so an edge can follow it.'],
                'name' => ['title' => 'Name', 'description' => 'Their full name.'],
                'title' => ['title' => 'Job title', 'description' => 'Their current title.'],
                'email' => ['title' => 'Email', 'description' => 'Their work email, when known.'],
                'company' => ['title' => 'Company', 'description' => 'Where they work.'],
                'company_domain' => ['title' => 'Company domain', 'description' => 'The company’s website.'],
                'linkedin_url' => ['title' => 'LinkedIn', 'description' => 'Their LinkedIn page.'],
                'location' => ['title' => 'Location', 'description' => 'City, state and country.'],
                'seniority' => ['title' => 'Seniority', 'description' => 'e.g. director or vp.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = trim((string) ($config['email'] ?? ''));
        $linkedin = trim((string) ($config['linkedin_url'] ?? ''));
        $first = trim((string) ($config['first_name'] ?? ''));
        $last = trim((string) ($config['last_name'] ?? ''));
        $company = trim((string) ($config['company'] ?? ''));

        if ($email === '' && $linkedin === '' && ($first === '' || $last === '')) {
            throw new InvalidArgumentException('Give an email, a LinkedIn page, or a first and last name (with a company, to be sure).');
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("“{$email}” is not an email address.");
        }

        $domain = $this->domain($company);

        $body = $this->call($run, $config, 'POST', self::API_URL.'/people/match', array_filter([
            'email' => $email ?: null,
            'linkedin_url' => $linkedin ?: null,
            'first_name' => $first ?: null,
            'last_name' => $last ?: null,
            'domain' => $domain,
            'organization_name' => $domain === null && $company !== '' ? $company : null,
        ], fn (mixed $value): bool => $value !== null));

        $person = (array) ($body['person'] ?? []);
        $organization = (array) ($person['organization'] ?? []);

        return [
            'found' => $person !== [],
            'result' => $person !== [] ? 'found' : 'none',
            'name' => $person['name'] ?? null,
            'title' => $person['title'] ?? null,
            'email' => $person['email'] ?? null,
            'company' => $organization['name'] ?? $person['organization_name'] ?? null,
            'company_domain' => $organization['primary_domain'] ?? $organization['website_url'] ?? null,
            'linkedin_url' => $person['linkedin_url'] ?? null,
            'location' => implode(', ', array_filter([$person['city'] ?? null, $person['state'] ?? null, $person['country'] ?? null])) ?: null,
            'seniority' => $person['seniority'] ?? null,
        ];
    }
}
