<?php

namespace App\Nodes\Integrations\Hunter;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class HunterVerifyEmailNode extends AbstractHunterNode
{
    public function type(): string
    {
        return 'hunter_verify_email';
    }

    public function name(): string
    {
        return 'Hunter: Verify Email';
    }

    public function description(): string
    {
        return 'Checks whether an email address is real and deliverable before you send to it, and flags disposable and catch-all addresses.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email address', 'Use {{templates}} to take it from an earlier step.', 'ada@example.com'),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['title' => 'Status', 'description' => 'valid, invalid, accept_all, webmail, disposable or unknown.'],
                'deliverable' => ['title' => 'Deliverable', 'description' => 'True only when Hunter says it will be delivered.'],
                'result' => ['title' => 'Branch', 'description' => 'deliverable, risky or undeliverable, so an edge can follow it.'],
                'score' => ['title' => 'Score', 'description' => 'From 0 to 100.'],
                'disposable' => ['title' => 'Disposable', 'description' => 'True for throwaway addresses.'],
                'webmail' => ['title' => 'Webmail', 'description' => 'True for a free mailbox such as Gmail.'],
                'accept_all' => ['title' => 'Catch-all', 'description' => 'True when the domain accepts any address, so it cannot be confirmed.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = strtolower(trim((string) ($config['email'] ?? '')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('“'.$email.'” is not an email address.');
        }

        $data = $this->data($this->call($run, $config, 'GET', self::API_URL.'/email-verifier', query: ['email' => $email]));
        $result = (string) ($data['result'] ?? '');
        $result = in_array($result, ['deliverable', 'undeliverable', 'risky'], true) ? $result : 'risky';

        return [
            'status' => $data['status'] ?? 'unknown',
            'deliverable' => $result === 'deliverable',
            'result' => $result,
            'score' => $this->percent($data['score'] ?? null),
            'disposable' => (bool) ($data['disposable'] ?? false),
            'webmail' => (bool) ($data['webmail'] ?? false),
            'accept_all' => (bool) ($data['accept_all'] ?? false),
        ];
    }
}
