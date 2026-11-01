<?php

namespace App\Nodes\Integrations\Apollo;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Nodes\Integrations\Concerns\CallsApiKeyService;

/**
 * Shared by the Apollo nodes: enrich a person or a company with what Apollo
 * knows. The key goes in the x-api-key header. Each enrichment spends Apollo
 * credits on the account that owns the key.
 */
abstract class AbstractApolloNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    use CallsApiKeyService;

    protected const string API_URL = 'https://api.apollo.io/api/v1';

    public function category(): string
    {
        return 'apollo';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    protected function serviceName(): string
    {
        return 'Apollo';
    }

    protected function authenticate(string $key, array $headers, array $query): array
    {
        return [[...$headers, 'x-api-key' => $key, 'Cache-Control' => 'no-cache'], $query];
    }

    /**
     * A bare domain, taken from a domain or a web address.
     */
    protected function domain(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('#^[a-z]+://#', '', $value) ?? $value;
        $value = preg_replace('#^www\.#', '', explode('/', $value)[0]) ?? $value;

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $value) === 1 ? $value : null;
    }
}
