<?php

namespace App\Nodes\Integrations\Hunter;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Nodes\Integrations\Concerns\CallsApiKeyService;
use InvalidArgumentException;

/**
 * Shared by the Hunter nodes: find and check professional email addresses and
 * look up a company from its domain. The key goes in the X-API-KEY header.
 * Every call spends Hunter credits, so each is a Read that costs the account
 * something; it is the account's own key, never ours.
 */
abstract class AbstractHunterNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    use CallsApiKeyService;

    protected const string API_URL = 'https://api.hunter.io/v2';

    public function category(): string
    {
        return 'hunter';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    protected function serviceName(): string
    {
        return 'Hunter';
    }

    protected function authenticate(string $key, array $headers, array $query): array
    {
        return [[...$headers, 'X-API-KEY' => $key], $query];
    }

    /**
     * A bare domain, taken from a domain, an address or an email.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function domain(array $config, string $key = 'domain'): string
    {
        $value = strtolower(trim((string) ($config[$key] ?? '')));
        $value = preg_replace('#^[a-z]+://#', '', $value) ?? $value;
        $value = str_contains($value, '@') ? substr($value, (int) strrpos($value, '@') + 1) : $value;
        $value = preg_replace('#^www\.#', '', explode('/', $value)[0]) ?? $value;

        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $value) !== 1) {
            throw new InvalidArgumentException('Give a company domain such as acme.com.');
        }

        return $value;
    }

    /**
     * What a call returns under `data`.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function data(array $body): array
    {
        return (array) ($body['data'] ?? []);
    }

    protected function percent(mixed $score): ?int
    {
        return is_numeric($score) ? (int) $score : null;
    }
}
