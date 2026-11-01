<?php

namespace App\Nodes\Integrations\Tavily;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Nodes\Integrations\Concerns\CallsApiKeyService;

/**
 * Shared by the Tavily nodes: a search API built for AI, which returns the
 * relevant text of each page and, optionally, a short answer. The key goes in
 * a bearer header.
 */
abstract class AbstractTavilyNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    use CallsApiKeyService;

    protected const string API_URL = 'https://api.tavily.com';

    public function category(): string
    {
        return 'tavily';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    protected function serviceName(): string
    {
        return 'Tavily';
    }

    protected function authenticate(string $key, array $headers, array $query): array
    {
        return [[...$headers, 'Authorization' => 'Bearer '.$key], $query];
    }

    /**
     * A list setting as clean strings.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function strings(array $config, string $key): array
    {
        $items = is_array($config[$key] ?? null) ? $config[$key] : preg_split('/[\r\n,]+/', (string) ($config[$key] ?? ''));

        return array_values(array_unique(array_filter(array_map(fn (mixed $item): string => trim((string) $item), $items ?: []), fn (string $item): bool => $item !== '')));
    }
}
