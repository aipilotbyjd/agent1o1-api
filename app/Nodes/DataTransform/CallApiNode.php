<?php

namespace App\Nodes\DataTransform;

use App\Contracts\DeclaresEffect;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Http\GuardedHttp;
use App\Services\Http\SsrfGuard;

/**
 * Generic outbound HTTP request node (Gumloop's "Call API"/`CallApiNode`).
 */
class CallApiNode implements DeclaresEffect, HasIcon, NodeContract
{
    public function __construct(private readonly SsrfGuard $ssrfGuard = new SsrfGuard) {}

    public function type(): string
    {
        return 'call_api';
    }

    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    public function name(): string
    {
        return 'Call API';
    }

    public function icon(): string
    {
        return 'api';
    }

    public function description(): string
    {
        return 'Makes a generic outbound HTTP request and returns the status, headers, and body.';
    }

    /**
     * A GET only reads; any other method may change something on a server
     * outside the workspace, and DELETE removes it.
     */
    public function effect(array $config): ActionEffect
    {
        return match (strtoupper((string) ($config['method'] ?? 'GET'))) {
            'GET' => ActionEffect::Read,
            'DELETE' => ActionEffect::Destructive,
            default => ActionEffect::External,
        };
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['method', 'url'],
            'properties' => [
                'method' => Field::select('Method', ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'DELETE' => 'DELETE'], default: 'GET'),
                'url' => [...Field::text('URL', null, 'https://api.example.com/v1/items'), 'format' => 'uri'],
                'headers' => Field::keyValue('Headers'),
                'body' => Field::json('Body', 'JSON request body (ignored for GET).'),
                'timeout_seconds' => Field::advanced(Field::integer('Timeout (seconds)', null, 30, 1, 120)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $method = strtoupper((string) ($config['method'] ?? 'GET'));

        $response = (new GuardedHttp($this->ssrfGuard))->send(
            $method,
            $config['url'],
            $config['headers'] ?? [],
            $method === 'GET' ? null : ($config['body'] ?? []),
            timeoutSeconds: (int) ($config['timeout_seconds'] ?? 30),
        );

        return [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->json() ?? $response->body(),
        ];
    }
}
