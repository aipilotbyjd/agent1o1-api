<?php

namespace App\Nodes\Integrations\Hunter;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class HunterDomainSearchNode extends AbstractHunterNode
{
    public function type(): string
    {
        return 'hunter_domain_search';
    }

    public function name(): string
    {
        return 'Hunter: Search Company Emails';
    }

    public function description(): string
    {
        return 'Lists the email addresses Hunter knows at a company, with each person’s name, role, department and how confident it is.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['domain'],
            'properties' => [
                ...$this->credentialFields(),
                'domain' => Field::text('Company domain', 'e.g. acme.com. A website address or an email also works.', 'acme.com'),
                'type' => Field::select('Kind', ['any' => 'Any', 'personal' => 'People', 'generic' => 'Role addresses (info@, sales@)'], null, 'any'),
                'department' => Field::advanced(Field::select('Department', ['any' => 'Any', 'executive' => 'Executive', 'it' => 'IT', 'finance' => 'Finance', 'management' => 'Management', 'sales' => 'Sales', 'legal' => 'Legal', 'support' => 'Support', 'hr' => 'HR', 'marketing' => 'Marketing', 'communication' => 'Communication', 'education' => 'Education', 'design' => 'Design', 'health' => 'Health', 'operations' => 'Operations'], null, 'any')),
                'limit' => Field::integer('Most emails', null, 10, 1, 100),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'emails' => ['title' => 'Emails', 'description' => 'Each: email, type, confidence (0 to 100), first_name, last_name, position, department, linkedin and phone.'],
                'count' => ['title' => 'Count', 'description' => 'How many emails were returned.'],
                'organization' => ['title' => 'Company', 'description' => 'The company’s name.'],
                'pattern' => ['title' => 'Pattern', 'description' => 'How addresses are built there, e.g. {first}.{last}.'],
                'domain' => ['title' => 'Domain', 'description' => 'The domain searched.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($config);
        $department = (string) ($config['department'] ?? 'any');
        $type = (string) ($config['type'] ?? 'any');

        $data = $this->data($this->call($run, $config, 'GET', self::API_URL.'/domain-search', query: [
            'domain' => $domain,
            'limit' => max(1, min(100, (int) ($config['limit'] ?? 10))),
            'type' => in_array($type, ['personal', 'generic'], true) ? $type : null,
            'department' => $department !== 'any' ? $department : null,
        ]));

        $emails = array_values(array_filter(array_map(fn (mixed $email): ?array => is_array($email) && filled($email['value'] ?? null) ? [
            'email' => (string) $email['value'],
            'type' => $email['type'] ?? null,
            'confidence' => $this->percent($email['confidence'] ?? null),
            'first_name' => $email['first_name'] ?? null,
            'last_name' => $email['last_name'] ?? null,
            'position' => $email['position'] ?? null,
            'department' => $email['department'] ?? null,
            'linkedin' => $email['linkedin'] ?? null,
            'phone' => $email['phone_number'] ?? null,
        ] : null, (array) ($data['emails'] ?? []))));

        return ['emails' => $emails, 'count' => count($emails), 'organization' => $data['organization'] ?? null, 'pattern' => $data['pattern'] ?? null, 'domain' => $domain];
    }
}
