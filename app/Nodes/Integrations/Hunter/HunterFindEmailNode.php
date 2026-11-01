<?php

namespace App\Nodes\Integrations\Hunter;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HunterFindEmailNode extends AbstractHunterNode
{
    public function type(): string
    {
        return 'hunter_find_email';
    }

    public function name(): string
    {
        return 'Hunter: Find Email';
    }

    public function description(): string
    {
        return 'Finds the most likely work email for a person from their name and their company’s domain, with a confidence score.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['domain'],
            'properties' => [
                ...$this->credentialFields(),
                'domain' => Field::text('Company domain', 'e.g. acme.com.', 'acme.com'),
                'first_name' => Field::text('First name', null, 'Ada'),
                'last_name' => Field::text('Last name', null, 'Lovelace'),
                'full_name' => Field::advanced(Field::text('Full name', 'Use instead of first and last name.')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'email' => ['title' => 'Email', 'description' => 'The most likely address, or empty when none was found.'],
                'found' => ['title' => 'Found', 'description' => 'True when an address was found.'],
                'result' => ['title' => 'Branch', 'description' => 'found or none, so an edge can follow it.'],
                'confidence' => ['title' => 'Confidence', 'description' => 'From 0 to 100.'],
                'position' => ['title' => 'Position', 'description' => 'Their role, when known.'],
                'verification' => ['title' => 'Verification', 'description' => 'valid, accept_all or unknown, when Hunter has checked it.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($config);
        $full = trim((string) ($config['full_name'] ?? ''));
        $first = trim((string) ($config['first_name'] ?? ''));
        $last = trim((string) ($config['last_name'] ?? ''));

        if ($full === '' && ($first === '' || $last === '')) {
            throw new InvalidArgumentException('Give a first and last name, or a full name.');
        }

        $data = $this->data($this->call($run, $config, 'GET', self::API_URL.'/email-finder', query: [
            'domain' => $domain,
            ...($full !== '' ? ['full_name' => $full] : ['first_name' => $first, 'last_name' => $last]),
        ]));

        $email = filled($data['email'] ?? null) ? (string) $data['email'] : null;

        return [
            'email' => $email,
            'found' => $email !== null,
            'result' => $email !== null ? 'found' : 'none',
            'confidence' => $this->percent($data['score'] ?? null),
            'position' => $data['position'] ?? null,
            'verification' => $data['verification']['status'] ?? null,
        ];
    }
}
